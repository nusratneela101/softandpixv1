<h2>Password Reset Request 🔑</h2>
<p>Hi <strong>{{user_name}}</strong>,</p>
<p>We received a request to reset your password. Click the button below to set a new password:</p>
<p style="text-align:center;">
  <a href="{{reset_url}}" class="btn">Reset Password</a>
</p>
<p style="background:#fff3cd;border-left:4px solid #ffc107;padding:12px 16px;border-radius:4px;font-size:13px;color:#856404;">
  ⚠️ This link will expire in <strong>{{expires_in}}</strong> minutes. If you did not request a password reset, please ignore this email.
</p>
<p>Best regards,<br><strong>The {{site_name}} Team</strong></p>
