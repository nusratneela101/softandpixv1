<h2>Payment Confirmed ✅</h2>
<p>Hi <strong>{{user_name}}</strong>,</p>
<p>We have received your payment. Thank you!</p>
<hr class="divider">
<table class="info-table">
  <tr><td>Invoice #:</td><td><strong>{{invoice_number}}</strong></td></tr>
  <tr><td>Amount Paid:</td><td><strong>{{currency}}{{amount}}</strong></td></tr>
  <tr><td>Payment Date:</td><td>{{payment_date}}</td></tr>
  <tr><td>Payment Method:</td><td>{{payment_method}}</td></tr>
</table>
<p style="text-align:center;">
  <a href="{{invoice_url}}" class="btn">Download Receipt</a>
</p>
<p>Best regards,<br><strong>The {{site_name}} Team</strong></p>
