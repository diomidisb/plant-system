<?php

declare(strict_types=1);

function ps_delete_own_account(PDO $db, int $id, int $version, string $email, string $password, bool $emailVerified = false): void
{
    $db->beginTransaction();
    try {
        $q = $db->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');
        $q->execute([$id]);
        $user = $q->fetch(PDO::FETCH_ASSOC);
        if (!$user || (int)$user['auth_version'] !== $version || !hash_equals($user['email'], $email)) {
            throw new RuntimeException('Your account changed. Sign in again before deleting it.');
        }
        if ($user['auth_provider'] === 'local') {
            if (!$user['password_hash'] || !password_verify($password, $user['password_hash'])) {
                throw new RuntimeException('The current password is incorrect.');
            }
        } elseif (!$emailVerified) {
            throw new RuntimeException('Verify the code sent to your account email first.');
        }
        $q = $db->prepare('SELECT id FROM devices WHERE user_id=? ORDER BY id FOR UPDATE');
        $q->execute([$id]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $device) {
            $guard = $db->prepare("SELECT id FROM irrigation_commands WHERE device_id=? AND status IN ('sent','started') LIMIT 1 FOR UPDATE");
            $guard->execute([$device]);
            if ($guard->fetchColumn()) {
                throw new RuntimeException('A device has an active or sent watering command. Stop watering and wait for confirmation before deleting your account.');
            }
        }
        foreach ([
            'DELETE r FROM sensor_readings r JOIN devices d ON d.id=r.device_id WHERE d.user_id=?',
            'DELETE r FROM irrigation_rules r JOIN devices d ON d.id=r.device_id WHERE d.user_id=?',
            'DELETE c FROM irrigation_commands c JOIN devices d ON d.id=c.device_id WHERE d.user_id=?',
            'UPDATE devices SET user_id=NULL,mqtt_topic=NULL,is_online=0 WHERE user_id=?',
            'DELETE FROM support_tickets WHERE user_id=?',
            "DELETE FROM auth_password_resets WHERE subject_type='user' AND subject_id=?",
            'DELETE FROM users WHERE id=?',
        ] as $sql) {
            $q = $db->prepare($sql);
            $q->execute([$id]);
        }
        // FK cascades remove remember tokens, email verification, Google identity and login IPs.
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function ps_mail_delete_code(string $email, string $code): void
{
    require_once dirname(__DIR__).'/vendor/autoload.php';
    require dirname(__DIR__).'/email_config.php';
    $mail = ps_mailer($email);
    $mail->Subject = 'Plant System Hellas — Account deletion / Διαγραφή λογαριασμού';
    $mail->Body = "Account deletion confirmation code: $code\nExpires in 10 minutes. This code does not delete your account automatically.\nIf you did not request this, ignore this email.\n\nΚωδικός επιβεβαίωσης διαγραφής: $code\nΙσχύει για 10 λεπτά. Η διαγραφή απαιτεί επιβεβαίωση μέσα στην εφαρμογή.";
    $mail->send();
}
