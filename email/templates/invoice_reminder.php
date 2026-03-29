<h2>Invoice Payment Reminder ⏰</h2>
<p>Hi <strong>{{user_name}}</strong>,</p>
<p>This is a reminder that the following invoice is <strong style="color:#dc3545;">overdue</strong>.</p>
<hr class="divider">
<table class="info-table">
  <tr><td>Invoice #:</td><td><strong>{{invoice_number}}</strong></td></tr>
  <tr><td>Amount Due:</td><td><strong>{{currency}}{{amount}}</strong></td></tr>
  <tr><td>Due Date:</td><td style="color:#dc3545;"><strong>{{due_date}}</strong></td></tr>
  <tr><td>Days Overdue:</td><td style="color:#dc3545;">{{days_overdue}} days</td></tr>
</table>
<p style="text-align:center;">
  <a href="{{invoice_url}}" class="btn">Pay Now</a>
</p>
<p>If you have already made this payment, please disregard this reminder.</p>
<p>Best regards,<br><strong>The {{site_name}} Team</strong></p>
