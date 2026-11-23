<?php

declare(strict_types=1);
require_once __DIR__ . '/admin-auth.php';
pa_require();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
$pdo = ps_db();
$adminPaths = ['overview' => '/admin.php','users' => '/admin-users.php','devices' => '/admin-devices.php','support' => '/admin-support.php','archive' => '/admin-support-archive.php','activity' => '/admin-activity.php'];
$section = defined('PS_ADMIN_SECTION') ? PS_ADMIN_SECTION : (string)($_POST['return_page'] ?? 'overview');
if (!isset($adminPaths[$section])) {
    $section = 'overview';
}

$error = '';
$success = (string) ($_SESSION['admin_message'] ?? '');
unset($_SESSION['admin_message']);

function pa_id(string $key): int
{
    $value = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT);
    if (!$value || $value < 1) {
        throw new RuntimeException('Invalid record.');
    }
    return (int) $value;
}

function pa_audit(PDO $pdo, string $action, string $entity, int $id): void
{
    $query = $pdo->prepare('INSERT INTO admin_audit_log (admin_name, action, entity_type, entity_id, source_ip) VALUES (?, ?, ?, ?, ?)');
    $query->execute([$_SESSION['admin_username'], $action, $entity, $id, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
}

function pa_device_idle(PDO $pdo, int $id): void
{
    $query = $pdo->prepare("SELECT id FROM irrigation_commands WHERE device_id = ? AND status IN ('sent', 'started') LIMIT 1 FOR UPDATE");
    $query->execute([$id]);
    if ($query->fetchColumn()) {
        throw new RuntimeException('This device has a sent or active watering command. Stop it and wait for confirmation first.');
    }
}

function pa_clear_history(PDO $pdo, int $id): void
{
    foreach (['irrigation_rules', 'irrigation_commands', 'sensor_readings'] as $table) {
        $query = $pdo->prepare("DELETE FROM $table WHERE device_id = ?");
        $query->execute([$id]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals(ps_csrf(), (string) ($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Your session expired. Refresh the page and try again.');
        }
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'logout') {
            $_SESSION = [];
            session_regenerate_id(true);
            header('Location: /login.php', true, 303);
            exit;
        }
        if ($action === 'send_reset') {
            $id = pa_id('user_id');
            $q = $pdo->prepare('SELECT * FROM users WHERE id=?');
            $q->execute([$id]);
            $user = $q->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                throw new RuntimeException('User not found.');
            }
            if (!ps_rate('admin-reset', (string)$id, 3, 3600)) {
                throw new RuntimeException('Too many reset emails. Try again in one hour.');
            }
            ps_admin_reset_mail($user);
            pa_audit($pdo, $action, 'user', $id);
            $_SESSION['admin_message'] = 'Password reset email sent. The link expires and can be used once.';
            header('Location: /admin-users.php', true, 303);
            exit;
        }
        $pdo->beginTransaction();
        if (in_array($action, ['edit_user', 'revoke_sessions', 'delete_user', 'block_user', 'unblock_user'], true)) {
            $id = pa_id('user_id');
            $query = $pdo->prepare('SELECT * FROM users WHERE id = ? FOR UPDATE');
            $query->execute([$id]);
            $user = $query->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                throw new RuntimeException('User not found.');
            }
            if ($action === 'edit_user') {
                $name = trim((string) ($_POST['name'] ?? ''));
                $surname = trim((string) ($_POST['surname'] ?? ''));
                $email = trim((string) ($_POST['email'] ?? ''));
                $phone = trim((string) ($_POST['phone'] ?? ''));
                $nationality = trim((string) ($_POST['nationality'] ?? ''));
                if ($name === '' || $surname === '' || mb_strlen($name) > 100 || mb_strlen($surname) > 100
                    || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255
                    || mb_strlen($phone) > 30 || mb_strlen($nationality) > 100) {
                    throw new RuntimeException('Check the name, surname, email and field lengths.');
                }
                if ($user['auth_provider'] !== 'local' && $email !== $user['email']) {
                    throw new RuntimeException('Changing a Google-linked email requires updating the identity integration; it is disabled here.');
                }
                $query = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
                $query->execute([$email, $id]);
                if ($query->fetchColumn()) {
                    throw new RuntimeException('That email belongs to another user.');
                }
                $query = $pdo->prepare('UPDATE users SET name = ?, surname = ?, email = ?, phone = ?, nationality = ?, auth_version = auth_version + 1 WHERE id = ?');
                $query->execute([$name, $surname, $email, $phone, $nationality, $id]);
                $message = 'User updated. Existing sessions were revoked.';
            } elseif ($action === 'block_user' || $action === 'unblock_user') {
                $q = $pdo->prepare('UPDATE users SET is_active=?,auth_version=auth_version+1 WHERE id=?');
                $q->execute([$action === 'unblock_user' ? 1 : 0,$id]);
                $message = $action === 'block_user' ? 'Account disabled. Existing sessions are blocked on their next request.' : 'Account reactivated. The user can sign in again.';
            } elseif ($action === 'revoke_sessions') {
                $query = $pdo->prepare('UPDATE users SET auth_version = auth_version + 1 WHERE id = ?');
                $query->execute([$id]);
                $message = 'User sessions revoked.';
            } else {
                if ((string) ($_POST['confirm_email'] ?? '') !== $user['email']) {
                    throw new RuntimeException('Type the exact user email to confirm deletion.');
                }
                $query = $pdo->prepare('SELECT id FROM devices WHERE user_id = ? ORDER BY id FOR UPDATE');
                $query->execute([$id]);
                foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $device) {
                    pa_device_idle($pdo, (int) $device);
                }
                $query = $pdo->prepare('DELETE r FROM sensor_readings r JOIN devices d ON d.id=r.device_id WHERE d.user_id=?');
                $query->execute([$id]);
                $query = $pdo->prepare('UPDATE irrigation_rules r JOIN devices d ON d.id=r.device_id SET r.enabled=0 WHERE d.user_id=?');
                $query->execute([$id]);
                $query = $pdo->prepare("UPDATE irrigation_commands c JOIN devices d ON d.id=c.device_id SET c.status='cancelled' WHERE d.user_id=? AND c.status IN ('pending','queued')");
                $query->execute([$id]);
                $query = $pdo->prepare('UPDATE devices SET user_id=NULL,mqtt_topic=NULL,is_online=0 WHERE user_id=?');
                $query->execute([$id]);
                $message = 'User deleted; devices retained without an owner.';
            }
            foreach (['auth_remember_tokens', 'auth_password_resets'] as $table) {
                $query = $pdo->prepare(($table === 'auth_password_resets' ? "DELETE FROM $table WHERE subject_type = 'user' AND subject_id = ?" : "DELETE FROM $table WHERE user_id = ?"));
                $query->execute([$id]);
            }
            if ($action === 'delete_user') {
                $query = $pdo->prepare('DELETE FROM users WHERE id = ?');
                $query->execute([$id]);
            }
            pa_audit($pdo, $action, 'user', $id);
        } elseif (in_array($action, ['assign_device', 'unassign_device', 'delete_device', 'rename_device'], true)) {
            $id = pa_id('device_id');
            // Lock target user first, matching user deletion's lock order.
            $owner = null;
            if ($action === 'assign_device') {
                $email = trim((string) ($_POST['target_email'] ?? ''));
                $query = $pdo->prepare('SELECT id FROM users WHERE email = ? FOR UPDATE');
                $query->execute([$email]);
                $owner = $query->fetchColumn();
                if (!$owner) {
                    throw new RuntimeException('Target user email not found.');
                }
            }
            $query = $pdo->prepare('SELECT * FROM devices WHERE id = ? FOR UPDATE');
            $query->execute([$id]);
            $device = $query->fetch(PDO::FETCH_ASSOC);
            if (!$device) {
                throw new RuntimeException('Device not found.');
            }
            if ($action === 'rename_device') {
                $name = trim((string) ($_POST['device_name'] ?? ''));
                if ($name === '' || mb_strlen($name) > 100) {
                    throw new RuntimeException('Device names must have 1–100 characters.');
                }
                $query = $pdo->prepare('UPDATE devices SET device_name = ? WHERE id = ?');
                $query->execute([$name, $id]);
                $message = 'Device renamed.';
            } else {
                if (($_POST['confirm'] ?? '') !== 'yes') {
                    throw new RuntimeException('Confirm the change and deletion of associated history.');
                }
                pa_device_idle($pdo, $id);
                if ($action === 'assign_device' && (int) $device['user_id'] === (int) $owner) {
                    throw new RuntimeException('This user already owns the device.');
                }
                if ($action === 'delete_device') {
                    $query = $pdo->prepare('INSERT INTO permanently_deleted_devices(device_uid) VALUES (?) ON DUPLICATE KEY UPDATE device_uid=VALUES(device_uid)');
                    $query->execute([$device['device_uid']]);
                    $query = $pdo->prepare('DELETE FROM devices WHERE id = ?');
                    $query->execute([$id]);
                    $message = 'Device permanently deleted. Automatic registration blocked.';
                } else {
                    if ($action === 'assign_device') {
                        $history = (string)($_POST['history'] ?? 'delete');
                        if (!in_array($history, ['keep','delete'], true)) {
                            throw new RuntimeException('Choose a valid history option.');
                        }
                        if ($history === 'delete') {
                            $q = $pdo->prepare('DELETE FROM sensor_readings WHERE device_id=?');
                            $q->execute([$id]);
                        }
                        $q = $pdo->prepare('UPDATE irrigation_rules SET enabled=0 WHERE device_id=?');
                        $q->execute([$id]);
                        $q = $pdo->prepare("UPDATE irrigation_commands SET status='cancelled' WHERE device_id=? AND status IN ('pending','queued')");
                        $q->execute([$id]);
                    } else {
                        $query = $pdo->prepare('DELETE FROM sensor_readings WHERE device_id=?');
                        $query->execute([$id]);
                        $query = $pdo->prepare('UPDATE irrigation_rules SET enabled=0 WHERE device_id=?');
                        $query->execute([$id]);
                        $query = $pdo->prepare("UPDATE irrigation_commands SET status='cancelled' WHERE device_id=? AND status IN ('pending','queued')");
                        $query->execute([$id]);
                    }
                    $topic = $owner ? 'plantsystem/user/' . $owner . '/device/' . $device['device_uid'] : null;
                    $query = $pdo->prepare('UPDATE devices SET user_id = ?, mqtt_topic = ? WHERE id = ?');
                    $query->execute([$owner ?: null, $topic, $id]);
                    $message = $owner ? 'Device transferred. Measurements were '.($history === 'keep' ? 'retained' : 'deleted').'. Old rules disabled. Reboot the ESP32 to refresh its subscriptions.' : 'Device removed from its user. Device Key retained; readings deleted and old rules disabled.';
                }
            }
            pa_audit($pdo, $action, 'device', $id);
        } elseif ($action === 'archive_ticket' || $action === 'restore_ticket') {
            $id = pa_id('ticket_id');
            $q = $pdo->prepare('SELECT status FROM support_tickets WHERE id=? FOR UPDATE');
            $q->execute([$id]);
            $status = $q->fetchColumn();
            if ($status === false) {
                throw new RuntimeException('Ticket not found.');
            }
            if ($action === 'archive_ticket' && $status !== 'closed') {
                throw new RuntimeException('Close the request before moving it to the archive.');
            }
            $q = $pdo->prepare('UPDATE support_tickets SET archived=?,updated_at=NOW() WHERE id=?');
            $q->execute([$action === 'archive_ticket' ? 1 : 0,$id]);
            pa_audit($pdo, $action, 'ticket', $id);
            $message = $action === 'archive_ticket' ? 'Closed request moved to the archive.' : 'Request restored to the inbox.';
        } elseif ($action === 'reply_ticket' || $action === 'delete_ticket') {
            $id = pa_id('ticket_id');
            $q = $pdo->prepare('SELECT id FROM support_tickets WHERE id=? FOR UPDATE');
            $q->execute([$id]);
            if (!$q->fetchColumn()) {
                throw new RuntimeException('Ticket not found.');
            }
            if ($action === 'delete_ticket') {
                if (($_POST['confirm'] ?? '') !== 'yes') {
                    throw new RuntimeException('Confirm permanent deletion of this request and its replies.');
                }
                $q = $pdo->prepare('DELETE FROM support_replies WHERE ticket_id=?');
                $q->execute([$id]);
                $q = $pdo->prepare('DELETE FROM support_tickets WHERE id=?');
                $q->execute([$id]);
                $message = 'Support request and replies permanently deleted.';
            } else {
                $reply = trim((string)($_POST['reply'] ?? ''));
                $words = preg_split('/\\s+/u', $reply, -1, PREG_SPLIT_NO_EMPTY);
                if (!$words || count($words) > 500 || mb_strlen($reply) > 20000) {
                    throw new RuntimeException('Write a reply of 1–500 words.');
                }
                $status = (string)($_POST['status'] ?? 'in_progress');
                if (!in_array($status, ['open','in_progress','closed'], true)) {
                    throw new RuntimeException('Invalid ticket status.');
                }
                $q = $pdo->prepare('INSERT INTO support_replies(ticket_id,admin_name,message) VALUES(?,?,?)');
                $q->execute([$id,$_SESSION['admin_username'],$reply]);
                $q = $pdo->prepare("UPDATE support_tickets SET archived=IF(?='closed',archived,0),status=?,updated_at=NOW() WHERE id=?");
                $q->execute([$status,$status,$id]);
                $message = 'Reply saved. The user can read it in My support requests.';
            }
            pa_audit($pdo, $action, 'ticket', $id);
        } elseif ($action === 'ticket_status') {
            $id = pa_id('ticket_id');
            $status = (string)($_POST['status'] ?? '');
            if (!in_array($status, ['open','in_progress','closed'], true)) {
                throw new RuntimeException('Invalid ticket status.');
            }
            $q = $pdo->prepare('SELECT id FROM support_tickets WHERE id=? FOR UPDATE');
            $q->execute([$id]);
            if (!$q->fetchColumn()) {
                throw new RuntimeException('Ticket not found.');
            }
            $q = $pdo->prepare("UPDATE support_tickets SET archived=IF(?='closed',archived,0),status=?,updated_at=NOW() WHERE id=?");
            $q->execute([$status,$status,$id]);
            pa_audit($pdo, $action, 'ticket', $id);
            $message = 'Support request updated.';
        } else {
            throw new RuntimeException('Unknown action.');
        }
        $pdo->commit();
        $_SESSION['admin_message'] = $message;
        header('Location: '.$adminPaths[$section], true, 303);
        exit;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof RuntimeException && !($exception instanceof PDOException)) {
            $error = $exception->getMessage();
        } else {
            error_log('Admin action: ' . $exception->getMessage());
            $error = 'The change could not be completed. No partial changes were saved.';
        }
    }
}

$search = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
$userPage = max(1, (int) ($_GET['up'] ?? 1));
$devicePage = max(1, (int) ($_GET['dp'] ?? 1));
$size = 20;
$userWhere = 'WHERE name LIKE ? OR surname LIKE ? OR email LIKE ?';
$query = $pdo->prepare("SELECT COUNT(*) FROM users $userWhere");
$query->execute(array_fill(0, 3, '%' . $search . '%'));
$userTotal = (int) $query->fetchColumn();
$userPage = min($userPage, max(1, (int) ceil($userTotal / $size)));
$offset = ($userPage - 1) * $size;
$query = $pdo->prepare("SELECT u.*, (SELECT COUNT(*) FROM devices d WHERE d.user_id=u.id) AS device_count FROM users u $userWhere ORDER BY id DESC LIMIT $size OFFSET $offset");
$query->execute(array_fill(0, 3, '%' . $search . '%'));
$users = $query->fetchAll(PDO::FETCH_ASSOC);
$deviceWhere = 'WHERE d.device_name LIKE ? OR d.device_uid LIKE ? OR u.email LIKE ?';
$query = $pdo->prepare("SELECT COUNT(*) FROM devices d LEFT JOIN users u ON u.id=d.user_id $deviceWhere");
$query->execute(array_fill(0, 3, '%' . $search . '%'));
$deviceTotal = (int) $query->fetchColumn();
$devicePage = min($devicePage, max(1, (int) ceil($deviceTotal / $size)));
$offset = ($devicePage - 1) * $size;
$query = $pdo->prepare("SELECT d.id,d.device_name,d.device_uid,d.user_id,d.is_online,d.last_seen,u.email AS owner_email FROM devices d LEFT JOIN users u ON u.id=d.user_id $deviceWhere ORDER BY d.id DESC LIMIT $size OFFSET $offset");
$query->execute(array_fill(0, 3, '%' . $search . '%'));
$devices = $query->fetchAll(PDO::FETCH_ASSOC);
$stats = $pdo->query('SELECT (SELECT COUNT(*) FROM users) AS users, (SELECT COUNT(*) FROM devices) AS devices, (SELECT COUNT(*) FROM devices WHERE is_online=1) AS online, (SELECT MAX(recorded_at) FROM sensor_readings) AS latest')->fetch(PDO::FETCH_ASSOC);
$audit = $pdo->query('SELECT admin_name,action,entity_type,entity_id,created_at FROM admin_audit_log ORDER BY id DESC LIMIT 30')->fetchAll(PDO::FETCH_ASSOC);

function pa_page_url(int $up, int $dp): string
{
    return $GLOBALS['adminPaths'][$GLOBALS['section']] . '?' . http_build_query(['q' => $GLOBALS['search'], 'up' => $up, 'dp' => $dp]);
}

$ticketPage = max(1, (int)($_GET['tp'] ?? 1));
$archived = $section === 'archive' ? 1 : 0;
$ticketTotal = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE archived=$archived")->fetchColumn();
$ticketPage = min($ticketPage, max(1, (int)ceil($ticketTotal / 20)));
$ticketOffset = ($ticketPage - 1) * 20;
$tickets = $pdo->query("SELECT * FROM support_tickets WHERE archived=$archived ORDER BY id DESC LIMIT 20 OFFSET $ticketOffset")->fetchAll(PDO::FETCH_ASSOC);
$openTickets = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status<>'closed'")->fetchColumn();

$adminReplies = [];
if ($tickets) {
    $ids = array_column($tickets, 'id');
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $q = $pdo->prepare("SELECT * FROM support_replies WHERE ticket_id IN ($marks) ORDER BY id");
    $q->execute($ids);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $reply) {
        $adminReplies[$reply['ticket_id']][] = $reply;
    }
}

$loginHistory = [];
if ($section === 'users' && $users) {
    $ids = array_column($users, 'id');
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $q = $pdo->prepare("SELECT user_id,INET6_NTOA(ip_address) ip,connected_at FROM (SELECT user_id,ip_address,connected_at,id,ROW_NUMBER() OVER(PARTITION BY user_id ORDER BY id DESC) rn FROM user_login_ips WHERE user_id IN ($marks)) h WHERE rn<=20 ORDER BY id DESC");
    $q->execute($ids);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $loginHistory[$row['user_id']][] = $row;
    }
}
