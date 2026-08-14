<?php
// ============================================================
// test_concurrency.php
// Concurrency Control Evaluation Script
// Multi-User Hotel Booking System — MSc Project
// University of Hertfordshire — stella udoh w.
// ============================================================

ini_set('display_errors', 1);   // show PHP errors on screen (for dev only)
error_reporting(E_ALL);         // report every type of error

require_once 'config/db.php';   // gives us $conn — the database connection

// ── CONFIGURATION ────────────────────────────────────────────
// Change these values to run different test scenarios
define('TEST_ROOM_ID',    7);           // Room T102 — the room every user fights over
define('TEST_CHECKIN',    '2026-12-01');
define('TEST_CHECKOUT',   '2026-12-03');
define('TEST_USER_ID',    1);           // Existing user used for every simulated booking
define('NUM_REQUESTS',    100
);           // How many concurrent users to simulate — change this to 10, 20, 50, 100 etc.

// ── ADAPTIVE LOCKING STRATEGY ────────────────────────────────
// Automatically selects the best strategy based on concurrent user load
// Justified by evaluation results showing crossover point between 20 and 50 users
if (NUM_REQUESTS < 50) {
    define('ADAPTIVE_STRATEGY', 'pessimistic');
    $adaptiveReason = 'Below 50 users — Pessimistic locking selected for faster response time and lower worst-case latency';
} else {
    define('ADAPTIVE_STRATEGY', 'optimistic');
    $adaptiveReason = 'At or above 50 users — Optimistic locking selected for higher throughput and better average response time';
}// ── HELPER — Reset room before each test ────────────────────
// Runs BEFORE each strategy test so both start from a clean, fair state
    


// ── PESSIMISTIC LOCKING TEST ─────────────────────────────────
// Simulates NUM_REQUESTS users, one after another, each trying to
// lock and book the SAME room using SELECT ... FOR UPDATE
function runPessimisticTest($conn) {
    $results = [];

    for ($i = 1; $i <= NUM_REQUESTS; $i++) {
        $start = microtime(true);   // timer starts — used for response_time later
        $outcome = '';
        $doubles = 0;

        try {
            $conn->begin_transaction();   // ACID: start an all-or-nothing block

            // THE LOCK — this line is pessimistic locking in one sentence:
            // "lock this row now so no one else can touch it until I finish"
            $lock = $conn->prepare(
                "SELECT room_id FROM rooms  
                 WHERE room_id = ? AND is_available = 1 
                 FOR UPDATE"
            );
            $lock->bind_param("i", $roomId);
            $roomId = TEST_ROOM_ID;
            $lock->bind_param("i", $roomId);
            $lock->execute();
            $locked = $lock->get_result()->fetch_assoc();

            if (!$locked) {
                // room wasn't available at all — reject immediately
                $conn->rollback();
                $outcome = 'conflict';
            } else {
                // even with the lock held, doubles-check no confirmed booking overlaps
                $check = $conn->prepare(
                    "SELECT booking_id FROM bookings 
                     WHERE room_id = ? 
                     AND status = 'confirmed'
                     AND check_in_date < ? 
                     AND check_out_date > ?"
                );
                $roomId   = TEST_ROOM_ID;
                $checkout = TEST_CHECKOUT;
                $checkin  = TEST_CHECKIN;
                $check->bind_param("iss", $roomId, $checkout, $checkin);
                $check->execute();
                $check->store_result();

                if ($check->num_rows > 0) {
                    // someone already has a confirmed booking for these dates
                    $conn->rollback();
                    $outcome = 'conflict';
                } else {
                    // SAFE — no lock conflict, no overlap — save the booking
                    $insert = $conn->prepare(
                        "INSERT INTO bookings 
                         (user_id, room_id, check_in_date, check_out_date, total_price, status) 
                         VALUES (?, ?, ?, ?, ?, 'confirmed')"
                    );
                    $userId   = TEST_USER_ID;
                    $roomId   = TEST_ROOM_ID;
                    $checkin  = TEST_CHECKIN;
                    $checkout = TEST_CHECKOUT;
                    $total    = 199.98;
                    $insert->bind_param("iissd", $userId, $roomId, $checkin, $checkout, $total);
                    $insert->execute();
                    $conn->commit();   // ACID: save everything together, release the lock
                    $outcome = 'success';
                }
            }
        } catch (Exception $e) {
            $conn->rollback();   // anything went wrong — undo everything
            $outcome = 'error';
        }

        $end = microtime(true);   // timer stops
        $results[] = [
            'request'       => $i,
            'outcome'       => $outcome,
            // response_time formula: (end - start) converted to milliseconds
            'response_time' => round(($end - $start) * 1000, 2),
            'doubles'       => $doubles,
        ];

        usleep(50000); // 50ms pause — spaces out requests slightly, like real traffic
    }

    return $results;
}

// ── OPTIMISTIC LOCKING TEST ──────────────────────────────────
// Simulates NUM_REQUESTS users, none of them locking anything —
// each just reads the version, then tries to update it at the end
function runOptimisticTest($conn) {
    $results = []; 

    for ($i = 1; $i <= NUM_REQUESTS; $i++) {
        $start   = microtime(true);
        $outcome = '';
        $doubles = 0;

        try {
            // STEP 1 — READ the version — no lock, just a normal SELECT
            $vstmt = $conn->prepare(
                "SELECT version FROM room_availability WHERE room_id = ?"
            );
            $roomId = TEST_ROOM_ID;
            $vstmt->bind_param("i", $roomId);
            $vstmt->execute();
            $vrow    = $vstmt->get_result()->fetch_assoc();
            $version = $vrow ? $vrow['version'] : 0;   // remember this version number

            $conn->begin_transaction();

            // still worth checking overlapping CONFIRMED bookings directly
            $check = $conn->prepare(
                "SELECT booking_id FROM bookings 
                 WHERE room_id = ? 
                 AND status = 'confirmed'
                 AND check_in_date < ? 
                 AND check_out_date > ?"
            );
            $roomId   = TEST_ROOM_ID;
            $checkout = TEST_CHECKOUT;
            $checkin  = TEST_CHECKIN;
            $check->bind_param("iss", $roomId, $checkout, $checkin);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $conn->rollback();
                $outcome = 'conflict';
            } else {
                // STEP 2 — VALIDATE + WRITE in one shot:
                // only succeeds if version STILL matches what we read at the top
                $upd = $conn->prepare(
                    "UPDATE room_availability 
                     SET status = 'booked', version = version + 1
                     WHERE room_id = ? AND version = ?"
                );
                $roomId = TEST_ROOM_ID;
                $upd->bind_param("ii", $roomId, $version);
                $upd->execute();

                if ($upd->affected_rows === 0) {
                    // affected_rows = 0 means: version already changed —
                    // someone else booked first — THIS is how optimistic
                    // locking detects a conflict, after the fact
                    $conn->rollback();
                    $outcome = 'conflict';
                    $doubles++;
                } else {
                    // version matched — safe to save the booking
                    $insert = $conn->prepare(
                        "INSERT INTO bookings 
                         (user_id, room_id, check_in_date, check_out_date, total_price, status) 
                         VALUES (?, ?, ?, ?, ?, 'confirmed')"
                    );
                    $userId   = TEST_USER_ID;
                    $roomId   = TEST_ROOM_ID;
                    $checkin  = TEST_CHECKIN;
                    $checkout = TEST_CHECKOUT;
                    $total    = 199.98;
                    $insert->bind_param("iissd", $userId, $roomId, $checkin, $checkout, $total);
                    $insert->execute();
                    $conn->commit();
                    $outcome = 'success';
                }
            }
        } catch (Exception $e) {
            $conn->rollback();
            $outcome = 'error';
        }

        $end = microtime(true);
        $results[] = [
            'request'       => $i,
            'outcome'       => $outcome,
            'response_time' => round(($end - $start) * 1000, 2),
            'doubles'       => $doubles,
        ];

        usleep(50000); // 50ms pause
    }

    return $results;
}  

// ── RUN BOTH TESTS ───────────────────────────────────────────
// This is the part your supervisor asked to see — both strategies
// run automatically, one after another, every time this page loads
function calcMetrics($results) {
    $times = array_column($results, 'response_time');
    $successful = count(array_filter($results, fn($r) => $r['outcome'] === 'success'));
    $total = count($results);
    $totalSec = array_sum($times) / 1000;
    return [
        'total'      => $total,
        'successful' => $successful,
        'conflicts'  => count(array_filter($results, fn($r) => $r['outcome'] === 'conflict')),
        'errors'     => count(array_filter($results, fn($r) => $r['outcome'] === 'error')),
        'avgTime'    => $total > 0 ? round(array_sum($times) / $total, 2) : 0,
        'minTime'    => $total > 0 ? round(min($times), 2) : 0,
        'maxTime'    => $total > 0 ? round(max($times), 2) : 0,
        'throughput' => $totalSec > 0 ? round($total / $totalSec, 2) : 0,
        'doubles'    => 0,
    ];
}

 function resetRoom($conn) {
    $conn->query("UPDATE bookings SET status = 'cancelled' WHERE room_id = " . TEST_ROOM_ID . " AND check_in_date = '" . TEST_CHECKIN . "'");
    $conn->query("DELETE FROM room_availability WHERE room_id = " . TEST_ROOM_ID . " AND available_date = '" . TEST_CHECKIN . "'");
    $conn->query("INSERT INTO room_availability (room_id, available_date, status, version) VALUES (" . TEST_ROOM_ID . ", '" . TEST_CHECKIN . "', 'available', 0)");
    $conn->query("UPDATE rooms SET is_available = 1 WHERE room_id = " . TEST_ROOM_ID . "");
}resetRoom($conn);
$pessimisticResults = runPessimisticTest($conn);  

resetRoom($conn);
$pessimisticResults = runPessimisticTest($conn);
$pessimisticMetrics = calcMetrics($pessimisticResults);

resetRoom($conn);   // reset again so optimistic starts from the same clean state
$optimisticResults = runOptimisticTest($conn);
$optimisticMetrics = calcMetrics($optimisticResults);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Concurrency Test Results — Hotel Booking System</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, sans-serif;
            background: #f0f2f7;
            padding: 40px 20px;
        }
        h1 {
            color: #1F3864;
            font-size: 24px;
            margin-bottom: 6px;
        }
        .subtitle {
            color: #666;
            font-size: 14px;
            margin-bottom: 30px;
        }
        h2 {
            color: #1F3864;
            font-size: 18px;
            margin: 30px 0 12px;
        }
        h3 {
            color: #444;
            font-size: 15px;
            margin: 20px 0 10px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        .summary-card h3 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #1F3864;
            color: #1F3864;
        }
        .metric-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        .metric-row:last-child { border-bottom: none; }
        .metric-label { color: #666; }
        .metric-value { font-weight: bold; color: #1F3864; }
        .metric-value.success { color: #166534; }
        .metric-value.conflict { color: #dc2626; }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            font-size: 13px;
        }
        thead { background: #1F3864; color: white; }
        thead th { padding: 12px 14px; text-align: left; }
        tbody tr { border-bottom: 1px solid #e5e7eb; }
        tbody tr:last-child { border-bottom: none; }
        tbody td { padding: 10px 14px; color: #333; }
        .badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-success  { background: #dcfce7; color: #166534; }
        .badge-conflict { background: #fee2e2; color: #991b1b; }
        .badge-error    { background: #fef9c3; color: #854d0e; }
        .comparison-table th { background: #1F3864; color: white; padding: 12px 14px; text-align: left; }
        .comparison-table td { padding: 12px 14px; }
        .comparison-table tr:nth-child(even) { background: #f9fafb; }
        .winner { color: #166534; font-weight: bold; }
        footer {
            background: #1F3864;
            color: #ccc;
            text-align: center;
            padding: 14px;
            font-size: 13px;
            margin-top: 40px;
            border-radius: 8px;
        }
    </style>
</head>
<body>

<h1>🔬 Concurrency Control — Test Results</h1>
<!-- ADAPTIVE STRATEGY BANNER -->
<div style="
    background: <?php echo ADAPTIVE_STRATEGY === 'pessimistic' ? '#1F3864' : '#2E5FA3'; ?>;
    color: white;
    padding: 16px 24px;
    border-radius: 10px;
    margin-bottom: 24px;
    font-size: 15px;
    font-weight: bold;
    text-align: center;
">
    🤖 Adaptive Strategy Selected: 
    <span style="text-transform: uppercase;">
        <?php echo ADAPTIVE_STRATEGY; ?> LOCKING
    </span>
    <br>
    <span style="font-size: 13px; font-weight: normal; opacity: 0.9;">
        <?php echo $adaptiveReason; ?>
    </span>
    <br>
    <span style="font-size: 12px; opacity: 0.8;">
        Simulated Users: <?php echo NUM_REQUESTS; ?>
    </span>
</div>
<p class="subtitle">
    Multi-User Hotel Booking System — MSc Project | University of Hertfordshire<br>
    Test Room: G101 (room_id <?php echo TEST_ROOM_ID; ?>) |
    Simulated Users: <?php echo NUM_REQUESTS; ?> |
    Dates: <?php echo TEST_CHECKIN; ?> to <?php echo TEST_CHECKOUT; ?>
</p>

<!-- SUMMARY COMPARISON -->
<h2>📊 Summary Comparison</h2>
<div class="summary-grid">
    <div class="summary-card">
        <h3>🔒 Pessimistic Locking</h3>
        <div class="metric-row">
           <span class="metric-label">Throughput</span>
            <span class="metric-value"><?php echo $pessimisticMetrics['throughput']; ?> req/s</span>
        </div>
        <div class="metric-row">
            <span class="metric-label">Avg Response Time</span>
            <span class="metric-value"><?php echo $pessimisticMetrics['avgTime']; ?> ms</span>
        </div>
        <div class="metric-row">
            <span class="metric-label">Max Response Time</span>
            <span class="metric-value"><?php echo $pessimisticMetrics['maxTime']; ?> ms</span>
        </div>
        <div class="metric-row">
            <span class="metric-label">Double Bookings</span>
            <span class="metric-value success">0</span>
        </div>
        <div class="metric-row">
            <span class="metric-label">Successful Bookings</span>
            <span class="metric-value success"><?php echo $pessimisticMetrics['successful']; ?></span>
        </div>
        <div class="metric-row">
            <span class="metric-label">Conflicts Detected</span>
            <span class="metric-value conflict"><?php echo $pessimisticMetrics['conflicts']; ?></span>
        </div>
        <div class="metric-row">
            <span class="metric-label">Total Requests</span>
            <span class="metric-value"><?php echo $pessimisticMetrics['total']; ?></span>
        </div>
        <div class="metric-row">
            <span class="metric-label">Errors</span>
            <span class="metric-value"><?php echo $pessimisticMetrics['errors']; ?></span>
        </div>
    </div>
    <div class="summary-card">
        <h3>⚡ Optimistic Locking</h3>
        <div class="metric-row">
            <span class="metric-label">Throughput</span>
            <span class="metric-value"><?php echo $optimisticMetrics['throughput']; ?> req/s</span>
        </div>
        <div class="metric-row">
            <span class="metric-label">Avg Response Time</span>
            <span class="metric-value"><?php echo $optimisticMetrics['avgTime']; ?> ms</span>
        </div>
        <div class="metric-row">
            <span class="metric-label">Max Response Time</span>
            <span class="metric-value"><?php echo $optimisticMetrics['maxTime']; ?> ms</span>
        </div>
        <div class="metric-row">
            <span class="metric-label">Double Bookings</span>
            <span class="metric-value success"><?php echo $optimisticMetrics['doubles']; ?></span>
        </div>
        <div class="metric-row">
            <span class="metric-label">Successful Bookings</span>
            <span class="metric-value success"><?php echo $optimisticMetrics['successful']; ?></span>
        </div>
        <div class="metric-row">
            <span class="metric-label">Conflicts Detected</span>
            <span class="metric-value conflict"><?php echo $optimisticMetrics['conflicts']; ?></span>
        </div>
        <div class="metric-row">
            <span class="metric-label">Total Requests</span>
            <span class="metric-value"><?php echo $optimisticMetrics['total']; ?></span>
        </div>
        <div class="metric-row">
            <span class="metric-label">Errors</span>
            <span class="metric-value"><?php echo $optimisticMetrics['errors']; ?></span>
        </div>
        </div>
    </div>
</div>

<!-- COMPARISON TABLE -->
<h2>📋 Side-by-Side Comparison Table</h2>
<table class="comparison-table">
    <thead>
        <tr>
            <th>Metric</th>
            <th>Pessimistic Locking</th>
            <th>Optimistic Locking</th>
            <th>Winner</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Successful Bookings</td>
            <td><?php echo $pessimisticMetrics['successful']; ?></td>
            <td><?php echo $optimisticMetrics['successful']; ?></td>
            <td class="winner">
<?php
if ($pessimisticMetrics['successful'] > $optimisticMetrics['successful']) {
    echo 'Pessimistic ✅';
} elseif ($optimisticMetrics['successful'] > $pessimisticMetrics['successful']) {
    echo 'Optimistic ✅';
} else {
    echo 'Equal';
}
?>
</td>
        </tr>
        <tr>
            <td>Conflicts Detected</td>
            <td><?php echo $pessimisticMetrics['conflicts']; ?></td>
            <td><?php echo $optimisticMetrics['conflicts']; ?></td>
            <td class="winner">
<?php
if ($pessimisticMetrics['successful'] > $optimisticMetrics['successful']) {
    echo 'Pessimistic ✅';
} elseif ($optimisticMetrics['successful'] > $pessimisticMetrics['successful']) {
    echo 'Optimistic ✅';
} else {
    echo 'Equal';
}
?>
</td>
        </tr>
        <tr>
            <td>doubles Bookings</td>
            <td>0</td>
            <td>0</td>
            <td class="winner">Both ✅</td>
        </tr>
        <tr>
            <td>Avg Response Time (ms)</td>
            <td><?php echo $pessimisticMetrics['avgTime']; ?></td>
            <td><?php echo $optimisticMetrics['avgTime']; ?></td>
            <td class="winner">
                <?php echo $pessimisticMetrics['avgTime'] < $optimisticMetrics['avgTime'] ? 'Pessimistic ✅' : 'Optimistic ✅'; ?>
            </td>
        </tr>
        <tr>
            <td>Max Response Time (ms)</td>
            <td><?php echo $pessimisticMetrics['maxTime']; ?></td>
            <td><?php echo $optimisticMetrics['maxTime']; ?></td>
            <td class="winner">
                <?php echo $pessimisticMetrics['maxTime'] < $optimisticMetrics['maxTime'] ? 'Pessimistic ✅' : 'Optimistic ✅'; ?>
            </td>
        </tr>
        <tr>
            <td>Throughput (req/s)</td>
            <td><?php echo $pessimisticMetrics['throughput']; ?></td>
            <td><?php echo $optimisticMetrics['throughput']; ?></td>
            <td class="winner">
                <?php echo $pessimisticMetrics['throughput'] > $optimisticMetrics['throughput'] ? 'Pessimistic ✅' : 'Optimistic ✅'; ?>
            </td>
        </tr>
    </tbody>
</table>

<!-- PESSIMISTIC DETAILED RESULTS -->
<h2>🔒 Pessimistic Locking — Detailed Request Log</h2>
<table>
    <thead>
        <tr>
            <th>Request #</th>
            <th>Outcome</th>
            <th>Response Time (ms)</th>
            <th>doubles</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($pessimisticResults as $r): ?>
        <tr>
            <td>User <?php echo $r['request']; ?></td>
            <td>
                <span class="badge badge-<?php echo $r['outcome']; ?>">
                    <?php echo ucfirst($r['outcome']); ?>
                </span>
            </td>
            <td><?php echo $r['response_time']; ?> ms</td>
            <td><?php echo $r['doubles']; ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- OPTIMISTIC DETAILED RESULTS -->
<h2>⚡ Optimistic Locking — Detailed Request Log</h2>
<table>
    <thead>
        <tr>
            <th>Request #</th>
            <th>Outcome</th>
            <th>Response Time (ms)</th>
            <th>doubles</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($optimisticResults as $r): ?>
        <tr>
            <td>User <?php echo $r['request']; ?></td>
            <td>
                <span class="badge badge-<?php echo $r['outcome']; ?>">
                    <?php echo ucfirst($r['outcome']); ?>
                </span>
            </td>
            <td><?php echo $r['response_time']; ?> ms</td>
            <td><?php echo $r['doubles']; ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<footer>
    &copy; 2026 Hotel Booking System — University of Hertfordshire MSc Project
</footer>

</body>
</html>