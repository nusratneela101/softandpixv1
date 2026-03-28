<?php
session_start();
require_once '../config/db.php';
require_once 'includes/auth.php';
requireClient();
$userId = $_SESSION['user_id'];
$notifications = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll();
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0")->execute([$userId]);
} catch (Exception $e) {}
function timeAgo($d){$t=time()-strtotime($d);if($t<60)return'just now';if($t<3600)return floor($t/60).' min ago';if($t<86400)return floor($t/3600).' hr ago';return floor($t/86400).' days ago';}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Notifications - Client Portal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<style>body{background:#f8fafc}.sidebar{background:linear-gradient(180deg,#1e3a5f,#2563eb);width:240px;min-height:100vh;position:fixed;top:0;left:0;z-index:100}.sidebar .nav-link{color:rgba(255,255,255,.85);padding:10px 20px;display:flex;align-items:center;gap:10px}.sidebar .nav-link:hover,.sidebar .nav-link.active{background:rgba(255,255,255,.15);color:#fff;border-radius:8px;margin:2px 8px;padding:10px 12px}.main-content{margin-left:240px;padding:24px}.notif-item{border-left:3px solid #dee2e6}.notif-item.unread{border-color:#2563eb;background:#eff6ff}</style>
</head><body>
<div class="sidebar">
  <div class="p-4 border-bottom" style="border-color:rgba(255,255,255,.2)!important;"><img src="/assets/img/SoftandPix -LOGO.png" alt="" style="max-height:32px;filter:brightness(10);"></div>
  <nav class="nav flex-column mt-2">
    <a class="nav-link" href="/client/"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a class="nav-link" href="/client/invoices.php"><i class="bi bi-receipt"></i> Invoices</a>
    <a class="nav-link" href="/client/chat.php"><i class="bi bi-chat-dots"></i> Chat</a>
    <a class="nav-link active" href="/client/notifications.php"><i class="bi bi-bell"></i> Notifications</a>
    <a class="nav-link" href="/profile.php"><i class="bi bi-person"></i> Profile</a>
    <hr style="border-color:rgba(255,255,255,.2);margin:8px 16px;">
    <a class="nav-link" href="/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </nav>
</div>
<div class="main-content">
  <h4 class="fw-bold mb-4"><i class="bi bi-bell me-2 text-primary"></i>Notifications</h4>
  <div class="card border-0 shadow-sm" style="border-radius:12px;">
    <?php if(empty($notifications)):?>
    <div class="card-body text-center py-5 text-muted"><i class="bi bi-bell-slash" style="font-size:3rem;opacity:.4;"></i><p class="mt-2">No notifications yet.</p></div>
    <?php else:?>
    <div class="list-group list-group-flush" style="border-radius:12px;">
    <?php foreach($notifications as $n):$icon=['project_assigned'=>'bi-kanban','deadline_updated'=>'bi-calendar-x','message'=>'bi-chat-dots','invoice'=>'bi-receipt','system'=>'bi-bell'][$n['type']??'system']??'bi-bell';?>
    <div class="list-group-item notif-item <?php echo!$n['is_read']?'unread':'';?> px-4 py-3">
      <div class="d-flex gap-3 align-items-start">
        <i class="bi <?php echo$icon;?> text-primary fs-5 mt-1"></i>
        <div class="flex-grow-1">
          <div class="fw-semibold"><?php echo h($n['title']??'Notification');?></div>
          <div class="text-muted"><?php echo h($n['message']??'');?></div>
          <?php if(!empty($n['link'])):?><a href="<?php echo h($n['link']);?>" class="small text-primary mt-1 d-inline-block">View →</a><?php endif;?>
        </div>
        <div class="text-muted small text-nowrap"><?php echo timeAgo($n['created_at']);?></div>
      </div>
    </div>
    <?php endforeach;?>
    </div>
    <?php endif;?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
