<?php
// backend/public/api.php
require_once 'db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = $_GET['action'] ?? '';
$pdo = Database::connect();

function detectBot($pdo, $ip, $userAgent, &$botType, &$botReason, &$botEvidence)
{
    $botType = '';
    $botReason = '';
    $botEvidence = [];
    $ua = strtolower($userAgent ?? '');

    $searchEngineKeywords = [
        'googlebot' => 'Google 搜索引擎爬虫',
        'bingbot' => 'Bing 搜索引擎爬虫',
        'baiduspider' => '百度搜索引擎爬虫',
        'yandexbot' => 'Yandex 搜索引擎爬虫',
        'sogou' => '搜狗搜索引擎爬虫',
        '360spider' => '360 搜索引擎爬虫',
        'bytespider' => '字节跳动搜索引擎爬虫',
        'duckduckbot' => 'DuckDuckGo 搜索引擎爬虫',
        'applebot' => 'Apple 搜索引擎爬虫',
        'slurp' => 'Yahoo 搜索引擎爬虫',
    ];
    foreach ($searchEngineKeywords as $kw => $desc) {
        if (strpos($ua, $kw) !== false) {
            $botType = 'search_engine';
            $botReason = $desc;
            $botEvidence['ua_match'] = $kw;
            return true;
        }
    }

    $stressKeywords = [
        'apachebench' => '压测工具 ApacheBench',
        'wrk' => '压测工具 wrk',
        'jmeter' => '压测工具 JMeter',
        'loadrunner' => '压测工具 LoadRunner',
        'locust' => '压测工具 Locust',
        'go-http-client' => '压测脚本/工具',
        'python-requests' => '脚本请求（疑似压测）',
        'curl/' => '脚本请求（疑似压测）',
        'httpclient' => '压测脚本/工具',
    ];
    foreach ($stressKeywords as $kw => $desc) {
        if (strpos($ua, $kw) !== false) {
            $botType = 'stress_tool';
            $botReason = $desc;
            $botEvidence['ua_match'] = $kw;
            return true;
        }
    }

    if ($ip) {
        $oneMinAgo = date('Y-m-d H:i:s', time() - 60);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM visitors WHERE ip = :ip AND created_at >= :t");
        $stmt->execute([':ip' => $ip, ':t' => $oneMinAgo]);
        $cnt = (int)$stmt->fetchColumn();
        if ($cnt >= 10) {
            $botType = 'malicious_refresh';
            $botReason = "1 分钟内同 IP 请求 {$cnt} 次，判定为恶意刷新/高频访问";
            $botEvidence['ip'] = $ip;
            $botEvidence['requests_last_60s'] = $cnt;
            return true;
        }
    }

    if (empty($ua) || $ua === '' || strlen($ua) < 10) {
        $botType = 'malicious_refresh';
        $botReason = 'User-Agent 异常（空或过短）';
        $botEvidence['ua_length'] = strlen($ua);
        return true;
    }

    return false;
}

try {
    switch ($action) {
        case 'collect':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid method');
            }
            $input = json_decode(file_get_contents('php://input'), true);

            // 获取真实 IP
            $ip = $_SERVER['REMOTE_ADDR'];
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
            } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
                $ip = $_SERVER['HTTP_X_REAL_IP'];
            }
            $ip = trim($ip);

            // 服务端 IP 定位（如果客户端没有提供）
            $country = $input['country'] ?? '';
            $city = $input['city'] ?? '';
            $isp = $input['isp'] ?? '';

            if (empty($country) && empty($city)) {
                // 尝试服务端获取 IP 定位
                $geoUrl = "http://ip-api.com/json/{$ip}?lang=zh-CN";
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 3,
                        'ignore_errors' => true
                    ]
                ]);
                $geoJson = @file_get_contents($geoUrl, false, $context);
                if ($geoJson) {
                    $geoData = json_decode($geoJson, true);
                    if ($geoData && $geoData['status'] === 'success') {
                        $country = $geoData['country'] ?? '';
                        $city = $geoData['city'] ?? '';
                        $isp = $geoData['isp'] ?? '';
                    }
                }
            }

            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $botType = '';
            $botReason = '';
            $botEvidence = [];
            $isBot = detectBot($pdo, $ip, $userAgent, $botType, $botReason, $botEvidence) ? 1 : 0;

            $data = [
                ':ip' => $ip,
                ':user_agent' => $userAgent,
                ':country' => $country,
                ':city' => $city,
                ':isp' => $isp,

                ':browser' => $input['browser'] ?? '未知',
                ':browser_version' => $input['browser_version'] ?? '',
                ':os' => $input['os'] ?? '未知',
                ':os_version' => $input['os_version'] ?? '',
                ':device_type' => $input['device_type'] ?? '桌面设备',

                ':screen_width' => $input['screen_width'] ?? 0,
                ':screen_height' => $input['screen_height'] ?? 0,
                ':window_width' => $input['window_width'] ?? 0,
                ':window_height' => $input['window_height'] ?? 0,

                ':language' => $input['language'] ?? '',
                ':timezone' => $input['timezone'] ?? '',
                ':platform' => $input['platform'] ?? '',
                ':cookie_enabled' => isset($input['cookie_enabled']) ? ($input['cookie_enabled'] ? 1 : 0) : 0,

                ':touch_points' => $input['touch_points'] ?? 0,
                ':device_memory' => $input['device_memory'] ?? 0,
                ':cpu_cores' => $input['cpu_cores'] ?? 0,
                ':connection_type' => $input['connection_type'] ?? '',

                ':referrer' => $input['referrer'] ?? '',
                ':remark' => '',

                ':is_bot' => $isBot,
                ':bot_type' => $botType,
                ':bot_reason' => $botReason,
                ':bot_evidence' => json_encode($botEvidence, JSON_UNESCAPED_UNICODE),
                ':bot_verified_by' => $isBot ? 'system_auto' : '',
                ':bot_verified_at' => $isBot ? date('Y-m-d H:i:s') : null,
            ];

            $sql = "INSERT INTO visitors (
                ip, user_agent, country, city, isp,
                browser, browser_version, os, os_version, device_type,
                screen_width, screen_height, window_width, window_height,
                language, timezone, platform, cookie_enabled,
                touch_points, device_memory, cpu_cores, connection_type,
                referrer, remark,
                is_bot, bot_type, bot_reason, bot_evidence, bot_verified_by, bot_verified_at
            ) VALUES (
                :ip, :user_agent, :country, :city, :isp,
                :browser, :browser_version, :os, :os_version, :device_type,
                :screen_width, :screen_height, :window_width, :window_height,
                :language, :timezone, :platform, :cookie_enabled,
                :touch_points, :device_memory, :cpu_cores, :connection_type,
                :referrer, :remark,
                :is_bot, :bot_type, :bot_reason, :bot_evidence, :bot_verified_by, :bot_verified_at
            )";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            $newId = (int)$pdo->lastInsertId();

            if ($isBot && $botType === 'malicious_refresh' && $ip) {
                $windowAgo = date('Y-m-d H:i:s', time() - 600);
                $batchEvidence = json_encode(array_merge($botEvidence, ['bulk_marked' => true, 'trigger_id' => $newId]), JSON_UNESCAPED_UNICODE);
                $upd = $pdo->prepare("UPDATE visitors SET is_bot = 1, bot_type = 'malicious_refresh', bot_reason = :reason, bot_evidence = :evidence, bot_verified_by = 'system_auto', bot_verified_at = :vat WHERE ip = :ip AND created_at >= :t AND (is_bot = 0 OR bot_verified_by = 'system_auto')");
                $upd->execute([
                    ':reason' => $botReason,
                    ':evidence' => $batchEvidence,
                    ':vat' => date('Y-m-d H:i:s'),
                    ':ip' => $ip,
                    ':t' => $windowAgo,
                ]);
            }

            echo json_encode(['status' => 'success', 'id' => $newId]);
            break;

        case 'list':
            $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
            $limit = 20;
            $offset = ($page - 1) * $limit;
            $search = $_GET['search'] ?? '';
            $botFilter = $_GET['bot_filter'] ?? 'all';

            $where = "WHERE 1=1";
            $params = [];

            if ($search) {
                $where .= " AND (ip LIKE :search OR remark LIKE :search OR city LIKE :search)";
                $params[':search'] = "%$search%";
            }
            if ($botFilter === 'real') {
                $where .= " AND is_bot = 0";
            } elseif ($botFilter === 'bot') {
                $where .= " AND is_bot = 1";
            }

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM visitors $where");
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT * FROM visitors $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
            $stmt->execute($params);
            $list = $stmt->fetchAll();

            echo json_encode([
                'status' => 'success',
                'data' => $list,
                'total' => $total,
                'page' => $page,
                'pages' => ceil($total / $limit)
            ]);
            break;

        case 'remark':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid method');
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? 0;
            $remark = $input['remark'] ?? '';

            if (!$id)
                throw new Exception('ID required');

            $stmt = $pdo->prepare("UPDATE visitors SET remark = :remark WHERE id = :id");
            $stmt->execute([':remark' => $remark, ':id' => $id]);

            echo json_encode(['status' => 'success']);
            break;

        case 'stats':
            $today = date('Y-m-d');

            $totalStmt = $pdo->query("SELECT COUNT(*) FROM visitors");
            $totalOps = (int)$totalStmt->fetchColumn();

            $realStmt = $pdo->query("SELECT COUNT(*) FROM visitors WHERE is_bot = 0");
            $totalReal = (int)$realStmt->fetchColumn();

            $todayOpsStmt = $pdo->prepare("SELECT COUNT(*) FROM visitors WHERE DATE(created_at) = :today");
            $todayOpsStmt->execute([':today' => $today]);
            $todayOps = (int)$todayOpsStmt->fetchColumn();

            $todayRealStmt = $pdo->prepare("SELECT COUNT(*) FROM visitors WHERE DATE(created_at) = :today AND is_bot = 0");
            $todayRealStmt->execute([':today' => $today]);
            $todayReal = (int)$todayRealStmt->fetchColumn();

            $botBreakdown = [
                'search_engine' => 0,
                'stress_tool' => 0,
                'malicious_refresh' => 0,
                'manual' => 0,
            ];
            $botStmt = $pdo->query("SELECT bot_type, COUNT(*) AS cnt FROM visitors WHERE is_bot = 1 GROUP BY bot_type");
            foreach ($botStmt->fetchAll() as $row) {
                $t = $row['bot_type'] ?: 'manual';
                if (!isset($botBreakdown[$t])) $botBreakdown[$t] = 0;
                $botBreakdown[$t] = (int)$row['cnt'];
            }

            $todayBotStmt = $pdo->prepare("SELECT bot_type, COUNT(*) AS cnt FROM visitors WHERE DATE(created_at) = :today AND is_bot = 1 GROUP BY bot_type");
            $todayBotStmt->execute([':today' => $today]);
            $todayBotBreakdown = [
                'search_engine' => 0,
                'stress_tool' => 0,
                'malicious_refresh' => 0,
                'manual' => 0,
            ];
            foreach ($todayBotStmt->fetchAll() as $row) {
                $t = $row['bot_type'] ?: 'manual';
                if (!isset($todayBotBreakdown[$t])) $todayBotBreakdown[$t] = 0;
                $todayBotBreakdown[$t] = (int)$row['cnt'];
            }

            echo json_encode([
                'status' => 'success',
                'ops_total' => $totalOps,
                'ops_today' => $todayOps,
                'real_total' => $totalReal,
                'real_today' => $todayReal,
                'bot_total' => $totalOps - $totalReal,
                'bot_today' => $todayOps - $todayReal,
                'bot_breakdown' => $botBreakdown,
                'today_bot_breakdown' => $todayBotBreakdown,
                'total' => $totalReal,
                'today' => $todayReal,
            ]);
            break;

        case 'detail':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) throw new Exception('ID required');
            $stmt = $pdo->prepare("SELECT * FROM visitors WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            if (!$row) throw new Exception('Not found');

            $auditStmt = $pdo->prepare("SELECT * FROM bot_audit_log WHERE visitor_id = :id ORDER BY created_at DESC");
            $auditStmt->execute([':id' => $id]);
            $row['audit_logs'] = $auditStmt->fetchAll();

            echo json_encode(['status' => 'success', 'data' => $row]);
            break;

        case 'mark_bot':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Invalid method');
            $input = json_decode(file_get_contents('php://input'), true);
            $id = (int)($input['id'] ?? 0);
            $isBot = (int)($input['is_bot'] ?? 0);
            $botType = $input['bot_type'] ?? '';
            $reason = trim($input['reason'] ?? '');
            $evidence = trim($input['evidence'] ?? '');
            $operator = trim($input['operator'] ?? 'admin');

            if (!$id) throw new Exception('ID required');
            if (!$reason) throw new Exception('改判理由不能为空');
            if (!$evidence) throw new Exception('证据留痕不能为空，请提供判定依据（如 UA 片段、日志、工单号等）');

            $stmt = $pdo->prepare("SELECT * FROM visitors WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $old = $stmt->fetch();
            if (!$old) throw new Exception('Not found');

            $oldIsBot = (int)$old['is_bot'];
            $oldBotType = $old['bot_type'] ?? '';
            $oldEvidence = $old['bot_evidence'] ?? '';

            $mergedEvidence = [
                'manual_evidence' => $evidence,
                'manual_operator' => $operator,
                'manual_reason' => $reason,
                'manual_at' => date('Y-m-d H:i:s'),
            ];
            if ($oldEvidence) {
                $mergedEvidence['original_evidence'] = $oldEvidence;
                $mergedEvidence['original_is_bot'] = $oldIsBot;
                $mergedEvidence['original_bot_type'] = $oldBotType;
                $mergedEvidence['original_reason'] = $old['bot_reason'] ?? '';
                $mergedEvidence['original_verified_by'] = $old['bot_verified_by'] ?? '';
            }
            $finalEvidence = json_encode($mergedEvidence, JSON_UNESCAPED_UNICODE);

            $upd = $pdo->prepare("UPDATE visitors SET is_bot = :is_bot, bot_type = :bot_type, bot_reason = :reason, bot_evidence = :evidence, bot_verified_by = :operator, bot_verified_at = :vat WHERE id = :id");
            $upd->execute([
                ':is_bot' => $isBot,
                ':bot_type' => $isBot ? $botType : '',
                ':reason' => $reason,
                ':evidence' => $finalEvidence,
                ':operator' => $operator,
                ':vat' => date('Y-m-d H:i:s'),
                ':id' => $id,
            ]);

            $ins = $pdo->prepare("INSERT INTO bot_audit_log (visitor_id, old_is_bot, old_bot_type, new_is_bot, new_bot_type, reason, evidence, operator) VALUES (:vid, :oib, :obt, :nib, :nbt, :reason, :evidence, :operator)");
            $ins->execute([
                ':vid' => $id,
                ':oib' => $oldIsBot,
                ':obt' => $oldBotType,
                ':nib' => $isBot,
                ':nbt' => $isBot ? $botType : '',
                ':reason' => $reason,
                ':evidence' => $finalEvidence,
                ':operator' => $operator,
            ]);

            echo json_encode(['status' => 'success']);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => '未知操作']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
