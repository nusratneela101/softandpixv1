<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{subject}}</title>
<style>
  body { margin:0; padding:0; background:#f4f6f9; font-family:Arial,Helvetica,sans-serif; }
  .email-wrap { max-width:600px; margin:0 auto; background:#ffffff; }
  .email-header { background:linear-gradient(135deg,#667eea,#764ba2); padding:30px 40px; text-align:center; }
  .email-header img { height:45px; }
  .email-header h1 { color:#fff; margin:12px 0 0; font-size:22px; letter-spacing:0.5px; }
  .email-body { padding:36px 40px; color:#333333; font-size:15px; line-height:1.7; }
  .email-body h2 { color:#333; font-size:20px; margin-top:0; }
  .email-footer { background:#f8f9fa; padding:24px 40px; text-align:center; color:#888; font-size:12px; border-top:1px solid #e9ecef; }
  .btn { display:inline-block; background:linear-gradient(135deg,#667eea,#764ba2); color:#fff !important; padding:12px 28px; border-radius:6px; text-decoration:none; font-weight:bold; margin:16px 0; }
  .divider { border:none; border-top:1px solid #e9ecef; margin:24px 0; }
  .info-table { width:100%; border-collapse:collapse; margin:16px 0; }
  .info-table td { padding:10px 12px; border-bottom:1px solid #e9ecef; font-size:14px; }
  .info-table td:first-child { font-weight:bold; color:#555; width:40%; }
</style>
</head>
<body>
<div class="email-wrap">
  <div class="email-header">
    <?php if (!empty($data['logo_url'])): ?>
    <img src="<?= htmlspecialchars($data['logo_url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($data['site_name'] ?? 'SoftandPix', ENT_QUOTES, 'UTF-8') ?>">
    <?php else: ?>
    <h1>{{site_name}}</h1>
    <?php endif; ?>
  </div>
  <div class="email-body">
    {{content}}
  </div>
  <div class="email-footer">
    <p>&copy; <?= date('Y') ?> {{site_name}}. All rights reserved.</p>
    <p><a href="{{site_url}}" style="color:#667eea;">{{site_url}}</a></p>
    <p style="color:#bbb;font-size:11px;">This email was sent to {{user_email}}. If you did not request this email, you can safely ignore it.</p>
  </div>
</div>
</body>
</html>
