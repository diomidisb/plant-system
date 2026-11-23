<?php require_once __DIR__ . '/php-scripts/admin.php'; ?>
<!doctype html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="96x96" href="/favicon.png">
    <link rel="shortcut icon" href="/favicon.ico">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Administration | Plant System</title>
    <link rel="stylesheet" href="/styles/admin.css?v=20261009c">
</head>
<body>
<header class="topbar">
    <a class="brand" href="/admin.php"><span class="brand-dot"></span> PLANT SYSTEM <small>ADMIN</small></a>
    <form method="post" action="/admin.php"><input type="hidden" name="return_page" value="<?= ps_e($section) ?>">
        <input type="hidden" name="csrf" value="<?= ps_e(ps_csrf()) ?>">
        <input type="hidden" name="action" value="logout">
        <span class="admin-name"><?= ps_e($_SESSION['admin_username']) ?></span>
        <button class="button secondary" type="submit">Sign out</button>
    </form>
</header>
<div class="workspace">
    <aside><p class="eyebrow">WORKSPACE</p><nav aria-label="Admin pages"><?php foreach(['overview'=>'Overview','users'=>'Users','devices'=>'Devices','support'=>'Support inbox','archive'=>'Closed request archive','activity'=>'Activity log'] as $key=>$label): ?><a href="<?= $adminPaths[$key] ?>" <?= $section===$key?'aria-current="page"':'' ?>><?= $label ?></a><?php endforeach; ?></nav><p class="sidebar-note">Administrator session expires after 30 minutes of inactivity.</p></aside>
    <main>
        <section id="overview" class="page-heading"><p class="eyebrow">ADMINISTRATION</p><h1><?= ps_e(['overview'=>'System overview','users'=>'User management','devices'=>'Device management','support'=>'Support inbox','archive'=>'Closed request archive','activity'=>'Activity log'][$section]) ?></h1><p>Manage accounts, device ownership and access from one place.</p></section>
        <?php if ($error): ?><div class="message error" role="alert"><?= ps_e($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="message success" role="status"><?= ps_e($success) ?></div><?php endif; ?>
        <?php if($section==='overview'): ?><div class="stats">
            <article><span>Users</span><strong><?= (int) $stats['users'] ?></strong></article>
            <article><span>Devices</span><strong><?= (int) $stats['devices'] ?></strong></article>
            <article><span>Reported online</span><strong><?= (int) $stats['online'] ?></strong></article>
            <article><span>Latest reading</span><strong class="date-value"><?= ps_e($stats['latest'] ?? 'No readings') ?></strong></article>
        </div>
        <?php endif; ?><?php if(in_array($section,['users','devices'],true)): ?><form method="get" action="<?= $adminPaths[$section] ?>" class="searchbar"><label for="search">Search users and devices</label><div><input id="search" name="q" value="<?= ps_e($search) ?>" placeholder="Name, email or device UID"><button class="button" type="submit">Search</button><a href="<?= $adminPaths[$section] ?>" class="button secondary">Reset</a></div></form>
        <?php endif; ?><?php if($section==='users'): ?><section id="users" class="section">
            <div class="section-heading"><h2>Users <span class="badge"><?= $userTotal ?></span></h2><span>Page <?= $userPage ?></span></div>
            <?php if (!$users): ?><p class="empty">No matching users.</p><?php endif; ?>
            <?php foreach ($users as $user): $id = (int) $user['id']; ?>
                <article class="record">
                    <div class="record-heading"><div><h3><?= ps_e($user['name'] . ' ' . $user['surname']) ?></h3><p><?= ps_e($user['email']) ?></p></div><span class="badge"><?= (int) $user['device_count'] ?> devices</span></div>
                    <div class="record-meta"><span class="badge <?= (int)$user['is_active'] ? 'online' : 'blocked' ?>"><?= (int)$user['is_active'] ? 'Active' : 'Disabled' ?></span> User #<?= $id ?> · <?= ps_e($user['auth_provider']) ?> · <?= (int) $user['email_verified'] ? 'Email verified' : 'Email not verified' ?></div>
                    <details><summary>Connection IP history</summary><p class="help">Only changes from the preceding login IP are recorded. Latest 20 entries. Times shown as stored by the server (UTC).</p><div class="table-wrap"><table><thead><tr><th>Connected at (UTC)</th><th>IP address</th></tr></thead><tbody><?php foreach($loginHistory[$id]??[] as $entry): ?><tr><td><?= ps_e($entry['connected_at']) ?></td><td><?= ps_e($entry['ip']) ?></td></tr><?php endforeach; ?><?php if(empty($loginHistory[$id])): ?><tr><td colspan="2">No connections recorded since logging was enabled.</td></tr><?php endif; ?></tbody></table></div></details>
                    <details><summary>Edit user</summary>
                        <form method="post" action="/admin.php" class="action-form"><input type="hidden" name="return_page" value="<?= ps_e($section) ?>">
                            <input type="hidden" name="csrf" value="<?= ps_e(ps_csrf()) ?>"><input type="hidden" name="action" value="edit_user"><input type="hidden" name="user_id" value="<?= $id ?>">
                            <div class="field-grid">
                                <?php foreach (['name' => 'Name', 'surname' => 'Surname', 'email' => 'Email', 'phone' => 'Phone', 'nationality' => 'Nationality'] as $field => $label): ?>
                                    <label for="u-<?= $id ?>-<?= $field ?>"><?= $label ?><input id="u-<?= $id ?>-<?= $field ?>" name="<?= $field ?>" type="<?= $field === 'email' ? 'email' : 'text' ?>" value="<?= ps_e($user[$field]) ?>" maxlength="<?= $field === 'email' ? 255 : ($field === 'phone' ? 30 : 100) ?>" <?= in_array($field, ['name', 'surname', 'email'], true) ? 'required' : '' ?> <?= $field === 'email' && $user['auth_provider'] !== 'local' ? 'readonly' : '' ?>></label>
                                <?php endforeach; ?>
                            </div><p class="help">Saving revokes existing login sessions and password-reset tokens.</p><button class="button" type="submit">Save user</button>
                        </form>
                    </details>
                    <div class="quick-actions">
                        <?php if ($user['auth_provider']==='local' && !empty($user['password_hash'])): ?><form method="post"><input type="hidden" name="return_page" value="<?= ps_e($section) ?>"><input type="hidden" name="csrf" value="<?= ps_e(ps_csrf()) ?>"><input type="hidden" name="action" value="send_reset"><input type="hidden" name="user_id" value="<?= $id ?>"><button class="button secondary">Send password reset email</button></form><?php else: ?><span class="help">Password managed by Google</span><?php endif; ?>
                        <form method="post"><input type="hidden" name="return_page" value="<?= ps_e($section) ?>"><input type="hidden" name="csrf" value="<?= ps_e(ps_csrf()) ?>"><input type="hidden" name="action" value="<?= (int)$user['is_active']?'block_user':'unblock_user' ?>"><input type="hidden" name="user_id" value="<?= $id ?>"><button class="button <?= (int)$user['is_active']?'danger':'secondary' ?>"><?= (int)$user['is_active']?'Disable account':'Reactivate account' ?></button></form>
                    </div>
                    <details><summary>Revoke login sessions</summary><form method="post" action="/admin.php" class="action-form"><input type="hidden" name="return_page" value="<?= ps_e($section) ?>"><input type="hidden" name="csrf" value="<?= ps_e(ps_csrf()) ?>"><input type="hidden" name="action" value="revoke_sessions"><input type="hidden" name="user_id" value="<?= $id ?>"><p class="help">Signs this user out when their next request checks authentication.</p><button class="button secondary" type="submit">Revoke sessions</button></form></details>
                    <details class="danger-zone"><summary>Delete user</summary><form method="post" action="/admin.php" class="action-form"><input type="hidden" name="return_page" value="<?= ps_e($section) ?>"><input type="hidden" name="csrf" value="<?= ps_e(ps_csrf()) ?>"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?= $id ?>"><p class="warning">Deletes this account and disconnects its <?= (int) $user['device_count'] ?> devices without deleting them. Measurements are deleted and old rules disabled. Sent or active watering blocks deletion.</p><label for="delete-u-<?= $id ?>">Type <?= ps_e($user['email']) ?> to confirm<input id="delete-u-<?= $id ?>" name="confirm_email" required autocomplete="off"></label><button class="button danger" type="submit">Permanently delete user</button></form></details>
                </article>
            <?php endforeach; ?>
            <div class="pagination"><?php if ($userPage > 1): ?><a href="<?= ps_e(pa_page_url($userPage - 1, $devicePage)) ?>#users">Previous</a><?php endif; ?><?php if ($userPage * $size < $userTotal): ?><a href="<?= ps_e(pa_page_url($userPage + 1, $devicePage)) ?>#users">Next users →</a><?php endif; ?></div>
        </section>
        <?php endif; ?><?php if($section==='devices'): ?><section id="devices" class="section">
            <div class="section-heading"><h2>Devices <span class="badge"><?= $deviceTotal ?></span></h2><span>Page <?= $devicePage ?></span></div>
            <?php if (!$devices): ?><p class="empty">No matching devices.</p><?php endif; ?>
            <?php foreach ($devices as $device): $id = (int) $device['id']; ?>
                <article class="record">
                    <div class="record-heading"><div><h3><?= ps_e($device['device_name']) ?></h3><p><?= ps_e($device['owner_email'] ?? 'Unassigned') ?></p></div><span class="badge <?= (int) $device['is_online'] ? 'online' : '' ?>"><?= (int) $device['is_online'] ? 'Online' : 'Offline' ?></span></div>
                    <div class="record-meta"><?= ps_e($device['device_uid']) ?> · Last seen: <?= ps_e($device['last_seen'] ?? 'Never') ?></div>
                    <details><summary>Rename device</summary><form method="post" action="/admin.php" class="action-form"><input type="hidden" name="return_page" value="<?= ps_e($section) ?>"><input type="hidden" name="csrf" value="<?= ps_e(ps_csrf()) ?>"><input type="hidden" name="action" value="rename_device"><input type="hidden" name="device_id" value="<?= $id ?>"><label for="d-name-<?= $id ?>">Device name<input id="d-name-<?= $id ?>" name="device_name" value="<?= ps_e($device['device_name']) ?>" maxlength="100" required></label><button class="button" type="submit">Save name</button></form></details>
                    <details><summary>Assign or transfer to a user</summary><form method="post" action="/admin.php" class="action-form"><input type="hidden" name="return_page" value="<?= ps_e($section) ?>"><input type="hidden" name="csrf" value="<?= ps_e(ps_csrf()) ?>"><input type="hidden" name="action" value="assign_device"><input type="hidden" name="device_id" value="<?= $id ?>"><label for="owner-<?= $id ?>">Target user's email<input id="owner-<?= $id ?>" type="email" name="target_email" required></label><label for="history-<?= $id ?>">Measurement history<select id="history-<?= $id ?>" name="history"><option value="delete">Transfer without history — delete measurements</option><option value="keep">Transfer with history — share existing measurements with the new owner</option></select></label><p class="help">Old irrigation rules are disabled and pending commands cancelled. Keeping history gives the new owner access to previous measurements. Reboot the ESP32 afterward.</p><label class="check"><input type="checkbox" name="confirm" value="yes" required> Confirm transfer with the selected history option</label><button class="button" type="submit">Assign device</button></form></details>
                    <details class="danger-zone"><summary>Remove from user or delete device</summary>
                        <?php foreach (['unassign_device' => 'Remove from user', 'delete_device' => 'Permanently delete device'] as $action => $label): ?>
                            <form method="post" action="/admin.php" class="action-form"><input type="hidden" name="return_page" value="<?= ps_e($section) ?>"><input type="hidden" name="csrf" value="<?= ps_e(ps_csrf()) ?>"><input type="hidden" name="action" value="<?= $action ?>"><input type="hidden" name="device_id" value="<?= $id ?>"><p class="warning"><?= $action === 'unassign_device' ? 'Keeps the device and Device Key. Deletes measurements, clears ownership and disables old rules.' : 'Permanently deletes the device and history, and blocks automatic registration of its UID.' ?></p><label class="check"><input type="checkbox" name="confirm" value="yes" required> Confirm the selected action</label><button class="button danger" type="submit"><?= $label ?></button></form>
                        <?php endforeach; ?>
                    </details>
                </article>
            <?php endforeach; ?>
            <div class="pagination"><?php if ($devicePage > 1): ?><a href="<?= ps_e(pa_page_url($userPage, $devicePage - 1)) ?>#devices">Previous</a><?php endif; ?><?php if ($devicePage * $size < $deviceTotal): ?><a href="<?= ps_e(pa_page_url($userPage, $devicePage + 1)) ?>#devices">Next devices →</a><?php endif; ?></div>
        </section>
        <?php endif; ?><?php if(in_array($section,['support','archive'],true)): ?><section id="support" class="section"><div class="section-heading"><h2><?= $section==='archive'?'Closed request archive':'Support inbox' ?> <span class="badge"><?= $section==='archive'?$ticketTotal.' archived':$openTickets.' unresolved' ?></span></h2><span>Page <?= $ticketPage ?> · <?= $ticketTotal ?> requests</span></div>
        <?php if (!$tickets): ?><p class="empty">No support requests yet.</p><?php endif; ?>
        <?php foreach($tickets as $ticket): ?><article class="record"><div class="record-heading"><div><h3>#<?= (int)$ticket['id'] ?> · <?= ps_e($ticket['subject']) ?></h3><p><?= ps_e($ticket['name']) ?> · <a href="mailto:<?= ps_e($ticket['email']) ?>"><?= ps_e($ticket['email']) ?></a></p></div><span class="badge status-<?= ps_e($ticket['status']) ?>"><?= ps_e(str_replace('_',' ',$ticket['status'])) ?></span></div><p class="record-meta"><?= ps_e($ticket['created_at']) ?> · <?= ps_e($ticket['phone'] ?? '') ?></p><p class="ticket-body"><?= nl2br(ps_e($ticket['message'])) ?></p><?php foreach($adminReplies[$ticket['id']]??[] as $reply): ?><div class="admin-reply"><strong><?= ps_e($reply['admin_name']) ?> · <?= ps_e($reply['created_at']) ?></strong><p><?= nl2br(ps_e($reply['message'])) ?></p></div><?php endforeach; ?>
<details><summary>Reply to this request</summary><form method="post" class="action-form"><input type="hidden" name="return_page" value="<?= ps_e($section) ?>"><input type="hidden" name="csrf" value="<?= ps_e(ps_csrf()) ?>"><input type="hidden" name="action" value="reply_ticket"><input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>"><label for="reply-<?= (int)$ticket['id'] ?>">Your reply (maximum 500 words)<textarea id="reply-<?= (int)$ticket['id'] ?>" name="reply" rows="5" maxlength="20000" required></textarea></label><label>Request status<select name="status"><option value="in_progress">In progress</option><option value="closed">Closed</option><option value="open">Open</option></select></label><p class="help">The reply will appear in the user's support page. No email is sent automatically.</p><button class="button">Save reply</button></form></details>
<?php if($section==='archive' || $ticket['status']==='closed'): ?><form method="post" class="quick-actions"><input type="hidden" name="return_page" value="<?= ps_e($section) ?>"><input type="hidden" name="csrf" value="<?= ps_e(ps_csrf()) ?>"><input type="hidden" name="action" value="<?= $section==='archive'?'restore_ticket':'archive_ticket' ?>"><input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>"><button class="button secondary"><?= $section==='archive'?'Restore to inbox':'Move closed request to archive' ?></button></form><?php endif; ?><details class="danger-zone"><summary>Delete request</summary><form method="post" class="action-form"><input type="hidden" name="return_page" value="<?= ps_e($section) ?>"><input type="hidden" name="csrf" value="<?= ps_e(ps_csrf()) ?>"><input type="hidden" name="action" value="delete_ticket"><input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>"><label class="check"><input type="checkbox" name="confirm" value="yes" required> Permanently delete this request and all its replies</label><button class="button danger">Delete request</button></form></details>
<form class="quick-actions" method="post"><input type="hidden" name="return_page" value="<?= ps_e($section) ?>"><input type="hidden" name="csrf" value="<?= ps_e(ps_csrf()) ?>"><input type="hidden" name="action" value="ticket_status"><input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>"><label>Status<select name="status"><?php foreach(['open'=>'Open','in_progress'=>'In progress','closed'=>'Closed'] as $value=>$label): ?><option value="<?= $value ?>" <?= $ticket['status']===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label><button class="button secondary">Update status</button></form></article><?php endforeach; ?></section>
        <div class="pagination"><?php if($ticketPage>1): ?><a href="<?= $adminPaths[$section] ?>?tp=<?= $ticketPage-1 ?>">Previous</a><?php endif; ?><?php if($ticketPage*20<$ticketTotal): ?><a href="<?= $adminPaths[$section] ?>?tp=<?= $ticketPage+1 ?>">Next requests</a><?php endif; ?></div><?php endif; ?><?php if($section==='activity'): ?><section id="activity" class="section"><div class="section-heading"><h2>Admin activity</h2><span>Latest 30 changes</span></div><div class="table-wrap"><table><thead><tr><th>Time</th><th>Admin</th><th>Action</th><th>Record</th></tr></thead><tbody><?php foreach ($audit as $entry): ?><tr><td><?= ps_e($entry['created_at']) ?></td><td><?= ps_e($entry['admin_name']) ?></td><td><?= ps_e($entry['action']) ?></td><td><?= ps_e($entry['entity_type']) ?> #<?= (int) $entry['entity_id'] ?></td></tr><?php endforeach; ?><?php if (!$audit): ?><tr><td colspan="4">No admin changes yet.</td></tr><?php endif; ?></tbody></table></div></section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
