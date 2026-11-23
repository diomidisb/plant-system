<?php

declare(strict_types=1);
function ps_history(PDO $db, int $userId, int $deviceId, string $range): array
{
    $dates = [
        'today' => "sr.recorded_at >= CURDATE() AND sr.recorded_at < CURDATE()+INTERVAL 1 DAY",
        'yesterday' => "sr.recorded_at >= CURDATE()-INTERVAL 1 DAY AND sr.recorded_at < CURDATE()",
        '7days' => "sr.recorded_at >= NOW()-INTERVAL 7 DAY AND sr.recorded_at <= NOW()",
        '30days' => "sr.recorded_at >= NOW()-INTERVAL 30 DAY AND sr.recorded_at <= NOW()"
    ];
    if (!isset($dates[$range])) {
        throw new InvalidArgumentException('Invalid range');
    }
    $q = $db->prepare('SELECT id,device_name,device_uid FROM devices WHERE id=? AND user_id=?');
    $q->execute([$deviceId,$userId]);
    $device = $q->fetch(PDO::FETCH_ASSOC);
    if (!$device) {
        throw new DomainException('Device not found');
    }
    $minutes = in_array($range, ['today','yesterday'], true) ? 0 : 60;
    if ($minutes === 0) {
        $q = $db->prepare("SELECT sr.temperature,sr.humidity,sr.soil_moisture,sr.light_level,sr.recorded_at,1 sample_count FROM sensor_readings sr JOIN devices d ON d.id=sr.device_id WHERE sr.device_id=? AND d.user_id=? AND {$dates[$range]} ORDER BY sr.recorded_at,sr.id");
    } else {
        $bucket = "TIMESTAMPADD(SECOND,FLOOR(TIMESTAMPDIFF(SECOND,'2000-01-01',sr.recorded_at)/3600)*3600,'2000-01-01')";
        $q = $db->prepare("SELECT AVG(sr.temperature) temperature,AVG(sr.humidity) humidity,AVG(sr.soil_moisture) soil_moisture,AVG(sr.light_level) light_level,$bucket recorded_at,COUNT(*) sample_count FROM sensor_readings sr JOIN devices d ON d.id=sr.device_id WHERE sr.device_id=? AND d.user_id=? AND {$dates[$range]} GROUP BY $bucket ORDER BY recorded_at");
    }
    $q->execute([$deviceId,$userId]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        foreach (['temperature','humidity','soil_moisture','light_level'] as $field) {
            $row[$field] = $row[$field] === null ? null : round((float)$row[$field], 2);
        }
        $row['sample_count'] = (int)$row['sample_count'];
    }
    unset($row);
    return ['success' => true,'device' => ['id' => (int)$device['id'],'name' => $device['device_name'],'uid' => $device['device_uid']], 'range' => $range,'aggregation_minutes' => $minutes,'readings' => $rows];
}
