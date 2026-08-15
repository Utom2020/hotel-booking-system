 <?php
require_once 'config/db.php';
require_once 'config/locking.php';

define('TEST_ROOM_ID', 1);
define('TEST_CHECKIN', '2026-12-01');
define('TEST_CHECKOUT', '2026-12-03');

$levels = [10,20,30,40,50,60,70,80,90,100,110,120,130,140,150];

function resetRoom($conn) {
    $conn->query("UPDATE bookings SET status = 'cancelled' WHERE room_id = " . TEST_ROOM_ID . " AND check_in_date = '" . TEST_CHECKIN . "'");
    $conn->query("DELETE FROM room_availability WHERE room_id = " . TEST_ROOM_ID . " AND available_date = '" . TEST_CHECKIN . "'");
    $conn->query("INSERT INTO room_availability (room_id, available_date, status, version) VALUES (" . TEST_ROOM_ID . ", '" . TEST_CHECKIN . "', 'available', 0)");
    $conn->query("UPDATE rooms SET is_available = 1 WHERE room_id = " . TEST_ROOM_ID . "");
}

function runPessimistic($conn, $total) {
    $times = [];
    for ($i = 1; $i <= $total; $i++) {
        $start = microtime(true);
        try {
            $conn->begin_transaction();
            $conn->query("SELECT room_id FROM rooms WHERE room_id = " . TEST_ROOM_ID . " FOR UPDATE");
            $check = $conn->query("SELECT booking_id FROM bookings WHERE room_id = " . TEST_ROOM_ID . " AND status = 'confirmed' AND check_in_date = '" . TEST_CHECKIN . "'");
            if ($check->num_rows === 0) {
                $conn->query("INSERT INTO bookings (user_id, room_id, check_in_date, check_out_date, total_price, status) VALUES (1, " . TEST_ROOM_ID . ", '" . TEST_CHECKIN . "', '" . TEST_CHECKOUT . "', 200, 'confirmed')");
                $conn->query("UPDATE rooms SET is_available = 0 WHERE room_id = " . TEST_ROOM_ID);
            }
            $conn->commit();
        } catch (Exception $e) { $conn->rollback(); }
        $times[] = (microtime(true) - $start) * 1000;
    }
    return $times;
}

function runOptimistic($conn, $total) {
    $times = [];
    for ($i = 1; $i <= $total; $i++) {
        $start = microtime(true);
        try {
            $conn->begin_transaction();
            $res = $conn->query("SELECT version FROM room_availability WHERE room_id = " . TEST_ROOM_ID . " AND available_date = '" . TEST_CHECKIN . "' AND status = 'available'");
            if ($res && $row = $res->fetch_assoc()) {
                $ver = $row['version'];
                $upd = $conn->query("UPDATE room_availability SET status = 'booked', version = version + 1 WHERE room_id = " . TEST_ROOM_ID . " AND available_date = '" . TEST_CHECKIN . "' AND version = " . $ver);
                if ($conn->affected_rows > 0) {
                    $conn->query("INSERT INTO bookings (user_id, room_id, check_in_date, check_out_date, total_price, status) VALUES (1, " . TEST_ROOM_ID . ", '" . TEST_CHECKIN . "', '" . TEST_CHECKOUT . "', 200, 'confirmed')");
                }
            }
            $conn->commit();
        } catch (Exception $e) { $conn->rollback(); }
        $times[] = (microtime(true) - $start) * 1000;
    }
    return $times;
}

function calcMetrics($times) {
    $total = count($times);
    $totalSec = array_sum($times) / 1000;
    return [
        'throughput' => $totalSec > 0 ? round($total / $totalSec, 2) : 0,
        'avgTime'    => $total > 0 ? round(array_sum($times) / $total, 2) : 0,
        'maxTime'    => $total > 0 ? round(max($times), 2) : 0,
    ];
}

$results = [];
foreach ($levels as $level) {
    resetRoom($conn);
    $pTimes = runPessimistic($conn, $level);
    $pMetrics = calcMetrics($pTimes);

    resetRoom($conn);
    $oTimes = runOptimistic($conn, $level);
    $oMetrics = calcMetrics($oTimes);

    $results[] = [
        'users' => $level,
        'p' => $pMetrics,
        'o' => $oMetrics,
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
<title>All Levels Test</title>
<style>
body { font-family: Arial, sans-serif; padding: 30px; background: #f5f7fa; }
h1 { color: #1F3864; }
table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
th { background: #1F3864; color: white; padding: 10px 14px; font-size: 13px; }
td { padding: 9px 14px; font-size: 13px; border-bottom: 1px solid #eee; text-align: center; }
tr:nth-child(even) td { background: #EEF3FB; }
.winner { color: #1D9E75; font-weight: bold; }
</style>
</head>
<body>
<h1>Concurrency Test — All 15 User Levels</h1>
<p>Room: G101 | Dates: 2026-12-01 to 2026-12-03 | Equal intervals: 10 to 150</p>
<table>
<tr>
    <th>Users</th>
    <th>Pess Throughput</th>
    <th>Opt Throughput</th>
    <th>Pess Avg (ms)</th>
    <th>Opt Avg (ms)</th>
    <th>Pess Max (ms)</th>
    <th>Opt Max (ms)</th>
    <th>Winner (Throughput)</th>
</tr>
<?php foreach ($results as $r): ?>
<tr>
    <td><?php echo $r['users']; ?></td>
    <td><?php echo $r['p']['throughput']; ?></td>
    <td><?php echo $r['o']['throughput']; ?></td>
    <td><?php echo $r['p']['avgTime']; ?></td>
    <td><?php echo $r['o']['avgTime']; ?></td>
    <td><?php echo $r['p']['maxTime']; ?></td>
    <td><?php echo $r['o']['maxTime']; ?></td>
    <td class="winner"><?php echo $r['p']['throughput'] > $r['o']['throughput'] ? 'Pessimistic' : 'Optimistic'; ?></td>
</tr>
<?php endforeach; ?>
</table>
</body>
</html>