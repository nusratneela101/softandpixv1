<h2>New Invoice Created 📄</h2>
<p>Hi <strong>{{user_name}}</strong>,</p>
<p>A new invoice has been created for you.</p>
<hr class="divider">
<table class="info-table">
  <tr><td>Invoice #:</td><td><strong>{{invoice_number}}</strong></td></tr>
  <tr><td>Amount:</td><td><strong>{{currency}}{{amount}}</strong></td></tr>
  <tr><td>Due Date:</td><td>{{due_date}}</td></tr>
  <tr><td>Status:</td><td>{{status}}</td></tr>
</table>
<p style="text-align:center;">
  <a href="{{invoice_url}}" class="btn">View Invoice</a>
</p>
<p>Best regards,<br><strong>The {{site_name}} Team</strong></p>
