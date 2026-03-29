<h2>New Task Assigned 📋</h2>
<p>Hi <strong>{{user_name}}</strong>,</p>
<p>A new task has been assigned to you.</p>
<hr class="divider">
<table class="info-table">
  <tr><td>Task:</td><td><strong>{{task_title}}</strong></td></tr>
  <tr><td>Project:</td><td>{{project_name}}</td></tr>
  <tr><td>Priority:</td><td>{{priority}}</td></tr>
  <tr><td>Due Date:</td><td>{{due_date}}</td></tr>
  <tr><td>Assigned By:</td><td>{{assigned_by}}</td></tr>
</table>
<?php if (!empty($data['description'])): ?>
<p><strong>Description:</strong><br>{{description}}</p>
<?php endif; ?>
<p style="text-align:center;">
  <a href="{{task_url}}" class="btn">View Task</a>
</p>
<p>Best regards,<br><strong>The {{site_name}} Team</strong></p>
