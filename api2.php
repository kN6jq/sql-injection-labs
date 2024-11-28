<?php
// api.php
header('Content-Type: application/json');

// 数据库连接
try {
    $pdo = new PDO("mysql:host=localhost;dbname=security_lab", "root", "root");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die(json_encode(['error' => 'Connection failed: ' . $e->getMessage()]));
}

$action = $_REQUEST['action'] ?? '';

switch($action) {
    // 1. 不安全的用户登录 - 字符型注入
    case 'user_login':
        $username = $_POST['username'];
        $password = $_POST['password'];
        $sql = "SELECT * FROM users WHERE username='$username' AND password='$password'";
        $result = $pdo->query($sql);
        echo json_encode(['status' => $result->rowCount() > 0]);
        break;

    // 2. 安全的订单查询 - 参数化查询
    case 'get_order':
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
        $stmt->execute([
            $_GET['order_id'],
            $_GET['user_id']
        ]);
        echo json_encode(['order' => $stmt->fetch(PDO::FETCH_ASSOC)]);
        break;

    // 3. 不安全的商品排序 - ORDER BY注入
    case 'sort_products':
        $sort_field = $_GET['sort_field'];
        $sort_dir = $_GET['sort_dir'];
        $sql = "SELECT * FROM products ORDER BY $sort_field $sort_dir";
        $result = $pdo->query($sql);
        echo json_encode(['products' => $result->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // 4. 安全的评论添加 - 参数化查询
    case 'add_comment':
        $stmt = $pdo->prepare("INSERT INTO comments (product_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->execute([
            $_POST['product_id'],
            $_POST['user_id'],
            $_POST['content']
        ]);
        echo json_encode(['status' => true]);
        break;

    // 5. 不安全的优惠券验证 - 时间盲注
    case 'verify_coupon':
        $code = $_GET['code'];
        $sql = "SELECT * FROM coupons WHERE code='$code' AND IF(SUBSTR(discount_amount,1,1)=1,SLEEP(2),0)";
        $result = $pdo->query($sql);
        echo json_encode(['valid' => $result->rowCount() > 0]);
        break;

    // 6. 不安全的用户搜索 - LIKE注入
    case 'search_users':
        $email = $_GET['email'];
        $sql = "SELECT * FROM users WHERE email LIKE '%$email%'";
        $result = $pdo->query($sql);
        echo json_encode(['users' => $result->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // 7. 安全的购物车更新 - 事务和参数化
    case 'update_cart':
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?");
            $stmt->execute([
                $_POST['quantity'],
                $_POST['user_id'],
                $_POST['product_id']
            ]);
            $pdo->commit();
            echo json_encode(['status' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    // 8. 不安全的订单查询 - IN注入
    case 'order_history':
        $status = $_GET['status'];
        $sql = "SELECT * FROM orders WHERE status IN ($status)";
        $result = $pdo->query($sql);
        echo json_encode(['orders' => $result->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // 9. 安全的用户资料更新 - 参数化更新
    case 'update_profile':
        $stmt = $pdo->prepare("UPDATE users SET email = ?, phone = ? WHERE id = ?");
        $stmt->execute([
            $_POST['email'],
            $_POST['phone'],
            $_POST['user_id']
        ]);
        echo json_encode(['status' => true]);
        break;

    // 10. 不安全的商品分类统计 - GROUP BY注入
    case 'category_stats':
        $field = $_GET['field'];
        $sql = "SELECT $field, COUNT(*) as count FROM products GROUP BY $field";
        $result = $pdo->query($sql);
        echo json_encode(['stats' => $result->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // 11. 安全的权限检查 - 参数化查询
    case 'check_permission':
        $stmt = $pdo->prepare("SELECT * FROM user_roles WHERE user_id = ? AND role = ?");
        $stmt->execute([
            $_POST['user_id'],
            $_POST['role']
        ]);
        echo json_encode(['has_permission' => $stmt->rowCount() > 0]);
        break;

    // 12. 不安全的商品评分 - HAVING注入
    case 'product_ratings':
        $min_rating = $_GET['min_rating'];
        $sql = "SELECT product_id, AVG(rating) as avg_rating 
                FROM ratings GROUP BY product_id 
                HAVING avg_rating > $min_rating";
        $result = $pdo->query($sql);
        echo json_encode(['ratings' => $result->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // 13. 不安全的分页查询 - LIMIT注入
    case 'list_products':
        $page = $_GET['page'];
        $limit = $_GET['limit'];
        $sql = "SELECT * FROM products LIMIT $limit OFFSET $page";
        $result = $pdo->query($sql);
        echo json_encode(['products' => $result->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // 14. 安全的子查询 - 参数化查询
    case 'expensive_products':
        $stmt = $pdo->prepare("
            SELECT * FROM products 
            WHERE price > (SELECT AVG(price) FROM products WHERE category = ?)
        ");
        $stmt->execute([$_GET['category']]);
        echo json_encode(['products' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // 15. 不安全的用户删除 - 堆叠注入
    case 'delete_user':
        $user_id = $_POST['user_id'];
        $sql = "DELETE FROM users WHERE id=$user_id; INSERT INTO logs VALUES($user_id);";
        $pdo->query($sql);
        echo json_encode(['status' => true]);
        break;
}
?>