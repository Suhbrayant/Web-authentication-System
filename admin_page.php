<?php
require_once 'config.php';
requireAdmin();

$adminId   = $_SESSION['user_id'];
$adminName = $_SESSION['name'] ?? 'Admin';
$tab       = $_GET['tab'] ?? 'overview';

//  POST handlers

// Post new internship
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'post_internship') {
        $title   = $conn->real_escape_string(trim($_POST['title']));
        $company = $conn->real_escape_string(trim($_POST['company']));
        $loc     = $conn->real_escape_string(trim($_POST['location']));
        $type    = $conn->real_escape_string($_POST['type']);
        $domain  = $conn->real_escape_string(trim($_POST['domain']));
        $desc    = $conn->real_escape_string(trim($_POST['description']));
        $req     = $conn->real_escape_string(trim($_POST['requirements']));
        $stip    = $conn->real_escape_string(trim($_POST['stipend']));
        $dur     = $conn->real_escape_string(trim($_POST['duration']));
        $dead    = $conn->real_escape_string($_POST['deadline']);
        $slots   = (int)$_POST['slots'];
        $conn->query("INSERT INTO internships (title,company,location,type,domain,description,requirements,stipend,duration,deadline,slots,status,posted_by)
                      VALUES ('$title','$company','$loc','$type','$domain','$desc','$req','$stip','$dur','$dead',$slots,'open',$adminId)");
        $_SESSION['flash'] = "Internship posted successfully!";
        header("Location: admin_page.php?tab=internships"); exit();
    }

    if ($action === 'delete_internship') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM internships WHERE id=$id");
        $_SESSION['flash'] = "Internship removed.";
        header("Location: admin_page.php?tab=internships"); exit();
    }

    if ($action === 'update_status') {
        $appId   = (int)$_POST['app_id'];
        $status  = $conn->real_escape_string($_POST['status']);
        $conn->query("UPDATE applications SET status='$status' WHERE id=$appId");
        // Notify the student
        $r = $conn->query("SELECT student_id, internship_id FROM applications WHERE id=$appId");
        if ($row = $r->fetch_assoc()) {
            $ir = $conn->query("SELECT title FROM internships WHERE id={$row['internship_id']}");
            $irow = $ir->fetch_assoc();
            $msg = "Your application for \"" . $irow['title'] . "\" has been <strong>$status</strong>.";
            addNotification($conn, $row['student_id'], strip_tags($msg), $status === 'approved' ? 'success' : ($status === 'rejected' ? 'danger' : 'info'));
        }
        header("Location: admin_page.php?tab=applications"); exit();
    }

    if ($action === 'send_message') {
        $to   = (int)$_POST['receiver_id'];
        $subj = $conn->real_escape_string(trim($_POST['subject']));
        $body = $conn->real_escape_string(trim($_POST['body']));
        $conn->query("INSERT INTO messages (sender_id,receiver_id,subject,body) VALUES ($adminId,$to,'$subj','$body')");
        addNotification($conn, $to, "You have a new message from Admin: \"$subj\"", 'message');
        $_SESSION['flash'] = "Message sent!";
        header("Location: admin_page.php?tab=messages"); exit();
    }

    if ($action === 'close_internship') {
        $id = (int)$_POST['id'];
        $conn->query("UPDATE internships SET status='closed' WHERE id=$id");
        header("Location: admin_page.php?tab=internships"); exit();
    }
}

//  Mark notifications read ─
if (isset($_GET['mark_read'])) {
    $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$adminId");
    header("Location: admin_page.php?tab=notifications"); exit();
}

//  Data queries 
$totalStudents    = $conn->query("SELECT COUNT(*) c FROM users WHERE role='user'")->fetch_assoc()['c'];
$totalInternships = $conn->query("SELECT COUNT(*) c FROM internships WHERE status='open'")->fetch_assoc()['c'];
$totalApps        = $conn->query("SELECT COUNT(*) c FROM applications")->fetch_assoc()['c'];
$pendingApps      = $conn->query("SELECT COUNT(*) c FROM applications WHERE status='pending'")->fetch_assoc()['c'];
$unreadMsg        = unreadMessages($conn, $adminId);
$unreadNotif      = unreadNotifications($conn, $adminId);

$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — InternConnect</title>
<link rel="stylesheet" href="styles.css?v=2">

</head>
<body class="admin-dashboard">

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <span>InternConnect</span>
    <small>Admin Panel</small>
  </div>
  <nav>
    <div class="nav-section">Main</div>
    <a href="?tab=overview"       class="nav-item <?= $tab==='overview'       ? 'active':'' ?>"><span class="icon">📊</span> Overview</a>
    <a href="?tab=internships"    class="nav-item <?= $tab==='internships'    ? 'active':'' ?>"><span class="icon">💼</span> Internships</a>
    <a href="?tab=post_internship"class="nav-item <?= $tab==='post_internship'? 'active':'' ?>"><span class="icon">➕</span> Post New</a>

    <div class="nav-section">Students</div>
    <a href="?tab=applications"   class="nav-item <?= $tab==='applications'   ? 'active':'' ?>">
      <span class="icon">📋</span> Applications
      <?php if($pendingApps>0): ?><span class="badge warning"><?= $pendingApps ?></span><?php endif; ?>
    </a>
    <a href="?tab=students"       class="nav-item <?= $tab==='students'       ? 'active':'' ?>"><span class="icon">👥</span> Students</a>

    <div class="nav-section">Communication</div>
    <a href="?tab=messages"       class="nav-item <?= $tab==='messages'       ? 'active':'' ?>">
      <span class="icon">💬</span> Messages
      <?php if($unreadMsg>0): ?><span class="badge"><?= $unreadMsg ?></span><?php endif; ?>
    </a>
    <a href="?tab=notifications"  class="nav-item <?= $tab==='notifications'  ? 'active':'' ?>">
      <span class="icon">🔔</span> Notifications
      <?php if($unreadNotif>0): ?><span class="badge"><?= $unreadNotif ?></span><?php endif; ?>
    </a>
  </nav>
  <div class="sidebar-footer">
    <div class="admin-chip">
      <div class="avatar"><?= strtoupper(substr($adminName,0,1)) ?></div>
      <div class="admin-info">
        <strong><?= htmlspecialchars($adminName) ?></strong>
        <small>Administrator</small>
      </div>
    </div>
    <a href="logout.php" class="logout-btn">⏻ Sign out</a>
  </div>
</aside>

<!--  MAIN  -->
<main class="main">
  <div class="topbar">
    <h1><?= ucfirst(str_replace('_',' ',$tab)) ?></h1>
    <div class="topbar-actions">
      <?php if($unreadMsg>0): ?>
        <a href="?tab=messages" class="btn btn-ghost btn-sm">💬 <?= $unreadMsg ?> new</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="page-content">
    <?php if($flash): ?><div class="flash">✓ <?= htmlspecialchars($flash) ?></div><?php endif; ?>

    <!--  OVERVIEW  -->
    <?php if($tab === 'overview'): ?>
    <div class="stats-grid">
      <div class="stat-card"><div class="stat-label">Registered Students</div><div class="stat-value accent"><?= $totalStudents ?></div></div>
      <div class="stat-card"><div class="stat-label">Open Internships</div><div class="stat-value success"><?= $totalInternships ?></div></div>
      <div class="stat-card"><div class="stat-label">Total Applications</div><div class="stat-value"><?= $totalApps ?></div></div>
      <div class="stat-card"><div class="stat-label">Pending Review</div><div class="stat-value warning"><?= $pendingApps ?></div></div>
    </div>

    <!-- Recent applications -->
    <div class="card">
      <div class="card-header"><h2>Recent Applications</h2><a href="?tab=applications" class="btn btn-ghost btn-sm">View all →</a></div>
      <div class="card-body table-wrap">
        <table>
          <thead><tr><th>Student</th><th>Internship</th><th>Applied</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php
          $res = $conn->query("SELECT a.id, u.name, i.title, a.applied_at, a.status
            FROM applications a JOIN users u ON a.student_id=u.id JOIN internships i ON a.internship_id=i.id
            ORDER BY a.applied_at DESC LIMIT 8");
          while($row = $res->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td><?= htmlspecialchars($row['title']) ?></td>
              <td><?= date('d M Y', strtotime($row['applied_at'])) ?></td>
              <td><span class="pill pill-<?= $row['status'] ?>"><?= $row['status'] ?></span></td>
              <td><a href="?tab=applications" class="btn btn-ghost btn-sm">Review</a></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!--  INTERNSHIPS  -->
    <?php elseif($tab === 'internships'): ?>
    <div class="card">
      <div class="card-header">
        <h2>All Internship Postings</h2>
        <a href="?tab=post_internship" class="btn btn-primary btn-sm">➕ Post New</a>
      </div>
      <div class="card-body table-wrap">
        <table>
          <thead><tr><th>Title</th><th>Company</th><th>Location</th><th>Deadline</th><th>Slots</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
          <?php
          $res = $conn->query("SELECT i.*, COUNT(a.id) apps FROM internships i LEFT JOIN applications a ON i.id=a.internship_id GROUP BY i.id ORDER BY i.created_at DESC");
          while($row = $res->fetch_assoc()): ?>
            <tr>
              <td><strong><?= htmlspecialchars($row['title']) ?></strong><br><small style="color:var(--muted)"><?= htmlspecialchars($row['domain']) ?></small></td>
              <td><?= htmlspecialchars($row['company']) ?></td>
              <td><?= htmlspecialchars($row['location']) ?></td>
              <td><?= $row['deadline'] ? date('d M Y', strtotime($row['deadline'])) : '—' ?></td>
              <td><?= $row['slots'] ?> <small style="color:var(--muted)">(<?= $row['apps'] ?> applied)</small></td>
              <td><span class="pill pill-<?= $row['status'] ?>"><?= $row['status'] ?></span></td>
              <td style="display:flex;gap:6px;flex-wrap:wrap">
                <?php if($row['status']==='open'): ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="action" value="close_internship">
                  <input type="hidden" name="id" value="<?= $row['id'] ?>">
                  <button class="btn btn-warning btn-sm" type="submit">Close</button>
                </form>
                <?php endif; ?>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this internship?')">
                  <input type="hidden" name="action" value="delete_internship">
                  <input type="hidden" name="id" value="<?= $row['id'] ?>">
                  <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!--  POST INTERNSHIP  -->
    <?php elseif($tab === 'post_internship'): ?>
    <div class="card">
      <div class="card-header"><h2>Post a New Internship</h2></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="post_internship">
          <div class="form-grid">
            <div class="form-group"><label>Job Title *</label><input name="title" required placeholder="e.g. Web Development Intern"></div>
            <div class="form-group"><label>Company *</label><input name="company" required placeholder="e.g. TechCorp Cameroon"></div>
            <div class="form-group"><label>Location</label><input name="location" placeholder="e.g. Yaoundé, Cameroon"></div>
            <div class="form-group"><label>Type</label>
              <select name="type"><option>On-site</option><option>Remote</option><option>Hybrid</option></select>
            </div>
            <div class="form-group"><label>Domain / Field</label><input name="domain" placeholder="e.g. IT, Finance, Health..."></div>
            <div class="form-group"><label>Stipend</label><input name="stipend" placeholder="e.g. 50,000 XAF/month or Unpaid"></div>
            <div class="form-group"><label>Duration</label><input name="duration" placeholder="e.g. 3 months"></div>
            <div class="form-group"><label>Application Deadline</label><input type="date" name="deadline"></div>
            <div class="form-group"><label>Available Slots</label><input type="number" name="slots" value="1" min="1"></div>
            <div class="form-group full"><label>Description *</label><textarea name="description" required placeholder="Describe the internship role and responsibilities..."></textarea></div>
            <div class="form-group full"><label>Requirements</label><textarea name="requirements" placeholder="Required skills, qualifications, level of study..."></textarea></div>
          </div>
          <div style="margin-top:20px">
            <button type="submit" class="btn btn-primary">🚀 Publish Internship</button>
          </div>
        </form>
      </div>
    </div>

    <!--  APPLICATIONS  -->
    <?php elseif($tab === 'applications'): ?>
    <div class="card">
      <div class="card-header"><h2>All Applications</h2></div>
      <div class="card-body table-wrap">
        <table>
          <thead><tr><th>Student</th><th>Internship</th><th>CV</th><th>Applied</th><th>Status</th><th>Update Status</th><th>Message</th></tr></thead>
          <tbody>
          <?php
          $res = $conn->query("SELECT a.id, u.name, u.email, i.title, a.cv_path, a.applied_at, a.status, a.student_id
            FROM applications a JOIN users u ON a.student_id=u.id JOIN internships i ON a.internship_id=i.id
            ORDER BY a.applied_at DESC");
          while($row = $res->fetch_assoc()): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($row['name']) ?></strong><br>
                <small style="color:var(--muted)"><?= htmlspecialchars($row['email']) ?></small>
              </td>
              <td><?= htmlspecialchars($row['title']) ?></td>
              <td>
                <?php if($row['cv_path']): ?>
                  <a href="uploads/<?= htmlspecialchars($row['cv_path']) ?>" target="_blank" class="btn btn-ghost btn-sm">📄 View CV</a>
                <?php else: ?>
                  <span style="color:var(--muted)">None</span>
                <?php endif; ?>
              </td>
              <td><?= date('d M Y', strtotime($row['applied_at'])) ?></td>
              <td><span class="pill pill-<?= $row['status'] ?>"><?= $row['status'] ?></span></td>
              <td>
                <form method="POST" style="display:flex;gap:6px;align-items:center">
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="app_id" value="<?= $row['id'] ?>">
                  <select name="status" style="width:110px">
                    <option value="pending"  <?= $row['status']==='pending'  ?'selected':'' ?>>Pending</option>
                    <option value="reviewed" <?= $row['status']==='reviewed' ?'selected':'' ?>>Reviewed</option>
                    <option value="approved" <?= $row['status']==='approved' ?'selected':'' ?>>Approved</option>
                    <option value="rejected" <?= $row['status']==='rejected' ?'selected':'' ?>>Rejected</option>
                  </select>
                  <button class="btn btn-primary btn-sm" type="submit">✓</button>
                </form>
              </td>
              <td>
                <a href="?tab=compose&to=<?= $row['student_id'] ?>&name=<?= urlencode($row['name']) ?>" class="btn btn-ghost btn-sm">✉ Message</a>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!--  STUDENTS  -->
    <?php elseif($tab === 'students'): ?>
    <div class="card">
      <div class="card-header"><h2>Registered Students</h2></div>
      <div class="card-body table-wrap">
        <table>
          <thead><tr><th>Name</th><th>Email</th><th>University</th><th>Level</th><th>Applications</th><th>Joined</th><th>Message</th></tr></thead>
          <tbody>
          <?php
          $res = $conn->query("SELECT u.*, COUNT(a.id) apps FROM users u LEFT JOIN applications a ON u.id=a.student_id WHERE u.role='user' GROUP BY u.id ORDER BY u.id DESC");
          while($row = $res->fetch_assoc()): ?>
            <tr>
              <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
              <td><?= htmlspecialchars($row['email']) ?></td>
              <td><?= htmlspecialchars($row['university'] ?? '—') ?></td>
              <td><?= htmlspecialchars($row['level'] ?? '—') ?></td>
              <td><?= $row['apps'] ?></td>
              <td><?= isset($row['created_at']) ? date('d M Y', strtotime($row['created_at'])) : '—' ?></td>
              <td><a href="?tab=compose&to=<?= $row['id'] ?>&name=<?= urlencode($row['name']) ?>" class="btn btn-ghost btn-sm">✉</a></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!--  MESSAGES  -->
    <?php elseif($tab === 'messages'): ?>
    <?php
    // Mark messages as read when viewing
    $conn->query("UPDATE messages SET is_read=1 WHERE receiver_id=$adminId");
    $msgs = $conn->query("SELECT m.*, u.name as sender_name FROM messages m JOIN users u ON m.sender_id=u.id WHERE m.receiver_id=$adminId ORDER BY m.sent_at DESC");
    ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
      <div>
        <div class="card">
          <div class="card-header"><h2>Inbox</h2></div>
          <div class="card-body">
            <div class="msg-list">
            <?php if($msgs->num_rows === 0): ?>
              <p style="color:var(--muted)">No messages yet.</p>
            <?php endif; ?>
            <?php while($m = $msgs->fetch_assoc()): ?>
              <div class="msg-item <?= $m['is_read'] ? '' : 'unread' ?>">
                <div class="msg-meta"><span>From: <?= htmlspecialchars($m['sender_name']) ?></span><span><?= date('d M, H:i', strtotime($m['sent_at'])) ?></span></div>
                <div class="msg-subject"><?= htmlspecialchars($m['subject'] ?: '(no subject)') ?></div>
                <div class="msg-body"><?= nl2br(htmlspecialchars($m['body'])) ?></div>
                <div style="margin-top:8px">
                  <a href="?tab=compose&to=<?= $m['sender_id'] ?>&name=<?= urlencode($m['sender_name']) ?>" class="btn btn-ghost btn-sm">↩ Reply</a>
                </div>
              </div>
            <?php endwhile; ?>
            </div>
          </div>
        </div>
      </div>
      <div>
        <div class="card">
          <div class="card-header"><h2>Sent Messages</h2></div>
          <div class="card-body">
            <div class="msg-list">
            <?php
            $sent = $conn->query("SELECT m.*, u.name as rname FROM messages m JOIN users u ON m.receiver_id=u.id WHERE m.sender_id=$adminId ORDER BY m.sent_at DESC LIMIT 20");
            if($sent->num_rows===0): ?><p style="color:var(--muted)">No sent messages.</p><?php endif; ?>
            <?php while($m=$sent->fetch_assoc()): ?>
              <div class="msg-item">
                <div class="msg-meta"><span>To: <?= htmlspecialchars($m['rname']) ?></span><span><?= date('d M, H:i', strtotime($m['sent_at'])) ?></span></div>
                <div class="msg-subject"><?= htmlspecialchars($m['subject'] ?: '(no subject)') ?></div>
                <div class="msg-body"><?= nl2br(htmlspecialchars($m['body'])) ?></div>
              </div>
            <?php endwhile; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!--  COMPOSE  -->
    <?php elseif($tab === 'compose'): ?>
    <?php
    $toId   = (int)($_GET['to'] ?? 0);
    $toName = htmlspecialchars($_GET['name'] ?? 'Student');
    ?>
    <div class="card" style="max-width:600px">
      <div class="card-header"><h2>Compose Message</h2></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="send_message">
          <input type="hidden" name="receiver_id" value="<?= $toId ?>">
          <div class="form-group" style="margin-bottom:14px">
            <label>To</label>
            <input value="<?= $toName ?>" readonly style="opacity:.6">
          </div>
          <div class="form-group" style="margin-bottom:14px">
            <label>Subject</label>
            <input name="subject" placeholder="e.g. Your application update">
          </div>
          <div class="form-group" style="margin-bottom:20px">
            <label>Message *</label>
            <textarea name="body" required rows="6" placeholder="Write your message here..."></textarea>
          </div>
          <button type="submit" class="btn btn-primary">📤 Send Message</button>
          <a href="?tab=messages" class="btn btn-ghost" style="margin-left:10px">Cancel</a>
        </form>
      </div>
    </div>

    <!--  NOTIFICATIONS  -->
    <?php elseif($tab === 'notifications'): ?>
    <div class="card">
      <div class="card-header">
        <h2>Notifications</h2>
        <a href="?mark_read=1" class="btn btn-ghost btn-sm">Mark all read</a>
      </div>
      <div class="card-body">
        <?php
        $notifs = $conn->query("SELECT * FROM notifications WHERE user_id=$adminId ORDER BY created_at DESC LIMIT 50");
        if($notifs->num_rows === 0): ?><p style="color:var(--muted)">No notifications.</p><?php endif; ?>
        <?php while($n = $notifs->fetch_assoc()): ?>
        <div class="notif-item">
          <div class="notif-dot <?= $n['is_read'] ? 'read' : '' ?>"></div>
          <div>
            <div class="notif-text"><?= htmlspecialchars($n['message']) ?></div>
            <div class="notif-time"><?= date('d M Y, H:i', strtotime($n['created_at'])) ?></div>
          </div>
        </div>
        <?php endwhile; ?>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /page-content -->
</main>

<script>
// Auto-refresh message badge every 30 seconds
setInterval(() => {
  fetch('ajax_poll.php?type=counts')
    .then(r => r.json())
    .then(d => {
      document.querySelectorAll('.msg-badge').forEach(el => el.textContent = d.messages || '');
    }).catch(() => {});
}, 30000);
</script>
</body>
</html>
