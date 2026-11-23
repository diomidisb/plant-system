<?php

if (!defined('PS_PUBLIC_PAGE')) {
    define('PS_PUBLIC_PAGE', true);
}
require_once __DIR__.'/auth-bootstrap.php';
$error = '';
$success = (string)($_SESSION['support_success'] ?? '');
unset($_SESSION['support_success']);
$supportUser = ps_support_user();
$fields = ['name' => '','email' => '','phone' => '','subject' => '','message' => ''];
if ($supportUser) {
    $fields['name'] = $supportUser['name'].' '.$supportUser['surname'];
    $fields['email'] = $supportUser['email'];
    $fields['phone'] = $supportUser['phone'] ?? '';
}
if (!$supportUser) {
    http_response_code(403);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $error = 'Please sign in to submit a support request. If you do not have an account, contact us by email.';
    }
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach ($fields as $key => $value) {
        $fields[$key] = trim((string)($_POST[$key] ?? ''));
    }
    try {
        if (!$supportUser) {
            throw new RuntimeException('Please sign in to submit a support request. If you do not have an account, contact us by email.');
        }
        $fields['email'] = $supportUser['email'];
        if (!hash_equals(ps_csrf(), (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Your session expired. Refresh the page and try again.');
        }
        if (!empty($_POST['website'])) {
            throw new RuntimeException('Unable to submit this request.');
        }
        $words = preg_split('/\s+/u', trim($fields['message']), -1, PREG_SPLIT_NO_EMPTY);
        if (!$words || count($words) > 500 || mb_strlen($fields['message']) > 20000) {
            throw new RuntimeException('Describe your problem in 1–500 words.');
        }
        if ($fields['name'] === '' || mb_strlen($fields['name']) > 150 || !filter_var($fields['email'], FILTER_VALIDATE_EMAIL) || strlen($fields['email']) > 255 || mb_strlen($fields['phone']) > 30 || $fields['subject'] === '' || mb_strlen($fields['subject']) > 150) {
            throw new RuntimeException('Check your contact details and subject.');
        }
        if (!ps_rate('support-user', (string)$supportUser['id'], 5, 3600) || !ps_rate('support-ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 15, 3600)) {
            throw new RuntimeException('Too many requests. Please try again in one hour.');
        }
        $q = ps_db()->prepare('INSERT INTO support_tickets(user_id,name,email,phone,subject,message) VALUES(?,?,?,?,?,?)');
        $q->execute([$supportUser['id'],$fields['name'],$fields['email'],$fields['phone'],$fields['subject'],$fields['message']]);
        $_SESSION['support_success'] = 'Your request #'.ps_db()->lastInsertId().' has been received. You can follow its status and read replies below.';
        header('Location: /support.php', true, 303);
        exit;
    } catch (Throwable $e) {
        $error = $e instanceof RuntimeException && !$e instanceof PDOException ? $e->getMessage() : 'We could not submit your request. Please try again later.';
        if ($e instanceof PDOException) {
            error_log('Support: '.$e->getMessage());
        }
    }
}

$myTickets = [];
$ticketReplies = [];
$ticketPage = max(1, (int)($_GET['page'] ?? 1));
$ticketTotal = 0;
if ($supportUser) {
    $q = ps_db()->prepare('SELECT COUNT(*) FROM support_tickets WHERE user_id=?');
    $q->execute([$supportUser['id']]);
    $ticketTotal = (int)$q->fetchColumn();
    $ticketPage = min($ticketPage, max(1, (int)ceil($ticketTotal / 20)));
    $offset = ($ticketPage - 1) * 20;
    $q = ps_db()->prepare("SELECT * FROM support_tickets WHERE user_id=? ORDER BY id DESC LIMIT 20 OFFSET $offset");
    $q->execute([$supportUser['id']]);
    $myTickets = $q->fetchAll(PDO::FETCH_ASSOC);
    if ($myTickets) {
        $ids = array_column($myTickets, 'id');
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $q = ps_db()->prepare("SELECT r.* FROM support_replies r JOIN support_tickets t ON t.id=r.ticket_id WHERE t.user_id=? AND t.id IN ($marks) ORDER BY r.id");
        $q->execute(array_merge([$supportUser['id']], $ids));
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $reply) {
            $ticketReplies[$reply['ticket_id']][] = $reply;
        }
    }
}
