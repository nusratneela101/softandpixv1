<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SoftandPix REST API v1 — Documentation</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f8f9fa; color: #212529; }
        pre  { background: #1e1e1e; color: #d4d4d4; padding: 1rem; border-radius: 6px; overflow-x: auto; font-size: .85rem; }
        code.inline { background: #e9ecef; padding: 2px 5px; border-radius: 4px; font-size: .9em; }
        .badge-get    { background: #198754; }
        .badge-post   { background: #0d6efd; }
        .badge-put    { background: #fd7e14; }
        .badge-delete { background: #dc3545; }
        .endpoint-card { border-left: 4px solid #0d6efd; margin-bottom: .75rem; }
        h2 { border-bottom: 2px solid #dee2e6; padding-bottom: .4rem; margin-top: 2rem; }
        .method-badge { font-size: .75rem; min-width: 60px; text-align: center; }
        a.anchor { display: block; position: relative; top: -70px; visibility: hidden; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark px-4 sticky-top">
    <a class="navbar-brand fw-bold" href="#">SoftandPix API <span class="badge bg-secondary fs-6">v1</span></a>
    <span class="text-secondary small">Base URL: <code class="text-white">/api/v1</code></span>
</nav>

<div class="container py-4">

    <!-- Overview -->
    <div class="alert alert-info mt-3">
        <strong>Base URL:</strong> <code>/api/v1/{resource}</code><br>
        <strong>Content-Type:</strong> All requests and responses use <code>application/json</code>.<br>
        <strong>Authentication:</strong> Pass <code>Authorization: Bearer &lt;token&gt;</code> in the request header.<br>
        <strong>Token:</strong> Obtain a token via <code>POST /api/v1/auth/login</code>. Tokens are valid for 30 days.
    </div>

    <!-- Auth -->
    <a class="anchor" id="auth"></a>
    <h2>🔐 Auth</h2>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-post method-badge me-2">POST</span><code>/api/v1/auth/login</code></span>
        <small class="text-muted">Authenticate and receive a Bearer token. No auth required.</small>
    </div>
    <pre>{
  "email": "user@example.com",
  "password": "secret123"
}

// Response 200
{
  "success": true,
  "token": "&lt;bearer-token&gt;",
  "user": { "id": 1, "name": "Alice", "email": "...", "role": "client" }
}</pre>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-post method-badge me-2">POST</span><code>/api/v1/auth/register</code></span>
        <small class="text-muted">Register a new client account. No auth required.</small>
    </div>
    <pre>{
  "name": "Alice",
  "email": "alice@example.com",
  "password": "secret123",
  "phone": "+1 555 0100"   // optional
}

// Response 201
{ "success": true, "token": "&lt;bearer-token&gt;", "user": { ... } }</pre>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-post method-badge me-2">POST</span><code>/api/v1/auth/forgot-password</code></span>
        <small class="text-muted">Request a password reset email. No auth required.</small>
    </div>
    <pre>{ "email": "alice@example.com" }

// Response 200
{ "success": true, "message": "If an account exists..." }</pre>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-get method-badge me-2">GET</span><code>/api/v1/auth/me</code></span>
        <small class="text-muted">Return the authenticated user's profile. <strong>Auth required.</strong></small>
    </div>
    <pre>// Response 200
{ "success": true, "user": { "id": 1, "name": "Alice", "role": "client", ... } }</pre>

    <!-- Projects -->
    <a class="anchor" id="projects"></a>
    <h2>📁 Projects</h2>
    <p class="text-muted small">All endpoints require authentication.</p>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-get method-badge me-2">GET</span><code>/api/v1/projects</code></span>
        <small class="text-muted">List projects. Filtered by role (admin sees all, clients/devs see their own). Optional: <code>?status=active</code></small>
    </div>
    <pre>// Response 200
{ "success": true, "data": [ { "id": 1, "name": "Website Redesign", "status": "active", ... } ] }</pre>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-get method-badge me-2">GET</span><code>/api/v1/projects/{id}</code></span>
        <small class="text-muted">Get details for a single project.</small>
    </div>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-post method-badge me-2">POST</span><code>/api/v1/projects</code></span>
        <small class="text-muted">Create a project. <strong>Admin only.</strong></small>
    </div>
    <pre>{
  "name": "New Project",
  "description": "...",
  "client_id": 5,
  "developer_id": 3,
  "deadline": "2025-12-31",
  "budget": 5000.00,
  "status": "active"
}</pre>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-put method-badge me-2">PUT</span><code>/api/v1/projects/{id}</code></span>
        <small class="text-muted">Update a project. Send only fields to change.</small>
    </div>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-delete method-badge me-2">DELETE</span><code>/api/v1/projects/{id}</code></span>
        <small class="text-muted">Delete a project. <strong>Admin only.</strong></small>
    </div>

    <!-- Tasks -->
    <a class="anchor" id="tasks"></a>
    <h2>✅ Tasks</h2>
    <p class="text-muted small">All endpoints require authentication.</p>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-get method-badge me-2">GET</span><code>/api/v1/tasks</code></span>
        <small class="text-muted">List tasks. Optional filters: <code>?project_id=&amp;status=&amp;priority=&amp;assigned_to=</code></small>
    </div>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-get method-badge me-2">GET</span><code>/api/v1/tasks/{id}</code></span>
        <small class="text-muted">Task details including comments.</small>
    </div>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-post method-badge me-2">POST</span><code>/api/v1/tasks</code></span>
        <small class="text-muted">Create a task.</small>
    </div>
    <pre>{
  "project_id": 1,
  "title": "Fix login bug",
  "description": "...",
  "priority": "high",     // low | medium | high | urgent
  "status": "pending",    // pending | in_progress | completed | on_hold
  "assigned_to": 3,
  "due_date": "2025-08-01"
}</pre>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-put method-badge me-2">PUT</span><code>/api/v1/tasks/{id}</code></span>
        <small class="text-muted">Update a task. Send only fields to change.</small>
    </div>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-delete method-badge me-2">DELETE</span><code>/api/v1/tasks/{id}</code></span>
        <small class="text-muted">Delete a task (admin or creator only).</small>
    </div>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-post method-badge me-2">POST</span><code>/api/v1/tasks/{id}/comments</code></span>
        <small class="text-muted">Add a comment to a task.</small>
    </div>
    <pre>{ "comment": "This is a comment." }</pre>

    <!-- Invoices -->
    <a class="anchor" id="invoices"></a>
    <h2>🧾 Invoices</h2>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-get method-badge me-2">GET</span><code>/api/v1/invoices</code></span>
        <small class="text-muted">List invoices (role-based). Optional: <code>?status=pending&amp;project_id=1</code></small>
    </div>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-get method-badge me-2">GET</span><code>/api/v1/invoices/{id}</code></span>
        <small class="text-muted">Invoice details including line items.</small>
    </div>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-post method-badge me-2">POST</span><code>/api/v1/invoices</code></span>
        <small class="text-muted">Create an invoice. <strong>Admin only.</strong></small>
    </div>
    <pre>{
  "client_id": 5,
  "project_id": 1,
  "invoice_number": "INV-2025-001",  // auto-generated if omitted
  "subtotal": 1000.00,
  "tax_percent": 10,
  "tax_amount": 100.00,
  "discount": 0,
  "total": 1100.00,
  "due_date": "2025-09-01",
  "notes": "Payment due in 30 days.",
  "status": "pending",
  "items": [
    { "description": "Web design", "quantity": 1, "unit_price": 1000.00 }
  ]
}</pre>

    <!-- Chat -->
    <a class="anchor" id="chat"></a>
    <h2>💬 Chat</h2>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-get method-badge me-2">GET</span><code>/api/v1/chat/messages</code></span>
        <small class="text-muted">Get messages. Params: <code>?user_id=3</code> or <code>?conversation_id=1</code>. Optional: <code>&amp;limit=50&amp;before_id=100</code></small>
    </div>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-post method-badge me-2">POST</span><code>/api/v1/chat/send</code></span>
        <small class="text-muted">Send a message. Creates a direct conversation automatically if needed.</small>
    </div>
    <pre>{
  "recipient_id": 3,        // OR "conversation_id": 5
  "message": "Hello!"
}</pre>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-get method-badge me-2">GET</span><code>/api/v1/chat/groups</code></span>
        <small class="text-muted">List group conversations the authenticated user is a member of.</small>
    </div>

    <!-- Time Tracking -->
    <a class="anchor" id="time"></a>
    <h2>⏱ Time Tracking</h2>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-post method-badge me-2">POST</span><code>/api/v1/time/start</code></span>
        <small class="text-muted">Start a timer. Only one active timer per user at a time.</small>
    </div>
    <pre>{ "project_id": 1, "task_id": 5, "description": "Working on login page" }</pre>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-post method-badge me-2">POST</span><code>/api/v1/time/stop</code></span>
        <small class="text-muted">Stop the running timer and create a time entry. No request body needed.</small>
    </div>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-get method-badge me-2">GET</span><code>/api/v1/time/entries</code></span>
        <small class="text-muted">List time entries. Optional: <code>?project_id=1&amp;from=2025-01-01&amp;to=2025-12-31&amp;user_id=3</code></small>
    </div>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-post method-badge me-2">POST</span><code>/api/v1/time/manual</code></span>
        <small class="text-muted">Add a manual time entry.</small>
    </div>
    <pre>{
  "project_id": 1,
  "task_id": 5,
  "start_time": "2025-07-01 09:00:00",
  "end_time":   "2025-07-01 11:30:00",
  "description": "Design work"
}</pre>

    <!-- Notifications -->
    <a class="anchor" id="notifications"></a>
    <h2>🔔 Notifications</h2>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-get method-badge me-2">GET</span><code>/api/v1/notifications</code></span>
        <small class="text-muted">List notifications. Optional: <code>?unread=1&amp;limit=20</code></small>
    </div>

    <div class="card endpoint-card ps-3 py-2 mb-2">
        <span><span class="badge badge-put method-badge me-2">PUT</span><code>/api/v1/notifications/{id}/read</code></span>
        <small class="text-muted">Mark a specific notification as read.</small>
    </div>

    <!-- Error Codes -->
    <h2>⚠️ Error Responses</h2>
    <p>All errors follow this structure:</p>
    <pre>{ "success": false, "error": "Descriptive error message." }</pre>

    <table class="table table-sm table-bordered">
        <thead class="table-dark">
            <tr><th>Status</th><th>Meaning</th></tr>
        </thead>
        <tbody>
            <tr><td>400</td><td>Bad Request — missing or invalid parameters</td></tr>
            <tr><td>401</td><td>Unauthorized — missing or invalid Bearer token</td></tr>
            <tr><td>403</td><td>Forbidden — authenticated but not permitted</td></tr>
            <tr><td>404</td><td>Not Found — resource or endpoint doesn't exist</td></tr>
            <tr><td>405</td><td>Method Not Allowed</td></tr>
            <tr><td>409</td><td>Conflict — e.g. duplicate email, timer already running</td></tr>
            <tr><td>429</td><td>Too Many Requests — rate limit exceeded</td></tr>
            <tr><td>500</td><td>Internal Server Error</td></tr>
        </tbody>
    </table>

</div>
</body>
</html>
