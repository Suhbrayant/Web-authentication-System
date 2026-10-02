<?php
require_once 'config.php';
requireUser();

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['name'] ?? 'Student';
$tab      = $_GET['tab'] ?? 'overview';

// ── POST handlers ─────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Apply for internship
    if ($action === 'apply') {
        $intId  = (int)$_POST['internship_id'];
        $cover  = $conn->real_escape_string(trim($_POST['cover_letter'] ?? ''));
        $cvPath = '';

        // Handle CV upload
        if (!empty($_FILES['cv']['name'])) {
            $ext  = strtolower(pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf','doc','docx'];
            if (in_array($ext, $allowed) && $_FILES['cv']['size'] < 5*1024*1024) {
                $fname  = 'cv_' . $userId . '_' . time() . '.' . $ext;
                $target = UPLOAD_DIR . $fname;
                if (move_uploaded_file($_FILES['cv']['tmp_name'], $target)) {
                    $cvPath = $fname;
                    // Update user's cv_path
                    $conn->query("UPDATE users SET cv_path='$cvPath' WHERE id=$userId");
                    // Log upload
                    $fesc = $conn->real_escape_string($fname);
                    $conn->query("INSERT INTO uploads (user_id,file_type,file_name,file_path) VALUES ($userId,'cv','$fesc','$fesc')");
                }
            } else {
                $_SESSION['flash'] = "CV must be PDF/DOC/DOCX and under 5MB.";
                header("Location: user_page.php?tab=apply&id=$intId"); exit();
            }
        } else {
            // Use existing CV on file
            $r = $conn->query("SELECT cv_path FROM users WHERE id=$userId");
            $cvPath = $r->fetch_assoc()['cv_path'] ?? '';
        }

        // Check not already applied
        $check = $conn->query("SELECT id FROM applications WHERE internship_id=$intId AND student_id=$userId");
        if ($check->num_rows > 0) {
            $_SESSION['flash'] = "You have already applied for this internship.";
        } else {
            $cvEsc = $conn->real_escape_string($cvPath);
            $conn->query("INSERT INTO applications (internship_id,student_id,cover_letter,cv_path) VALUES ($intId,$userId,'$cover','$cvEsc')");
            // Notify admin(s)
            $admins = $conn->query("SELECT id FROM users WHERE role='admin'");
            while ($adm = $admins->fetch_assoc()) {
                addNotification($conn, $adm['id'], "$userName applied for internship ID $intId.", 'info');
            }
            $_SESSION['flash'] = "Application submitted successfully!";
        }
        header("Location: user_page.php?tab=my_applications"); exit();
    }

    // Update profile
    if ($action === 'update_profile') {
        $phone  = $conn->real_escape_string(trim($_POST['phone'] ?? ''));
        $uni    = $conn->real_escape_string(trim($_POST['university'] ?? ''));
        $dept   = $conn->real_escape_string(trim($_POST['department'] ?? ''));
        $level  = $conn->real_escape_string(trim($_POST['level'] ?? ''));
        $conn->query("UPDATE users SET phone='$phone', university='$uni', department='$dept', level='$level' WHERE id=$userId");
        $_SESSION['flash'] = "Profile updated!";
        header("Location: user_page.php?tab=profile"); exit();
    }

    // Reply to message
    if ($action === 'send_message') {
        $to   = (int)$_POST['receiver_id'];
        $subj = $conn->real_escape_string(trim($_POST['subject']));
        $body = $conn->real_escape_string(trim($_POST['body']));
        $conn->query("INSERT INTO messages (sender_id,receiver_id,subject,body) VALUES ($userId,$to,'$subj','$body')");
        $_SESSION['flash'] = "Message sent!";
        header("Location: user_page.php?tab=messages"); exit();
    }
}

// Mark notifications read
if (isset($_GET['mark_read'])) {
    $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$userId");
    header("Location: user_page.php?tab=notifications"); exit();
}
// Mark messages read
if ($tab === 'messages') {
    $conn->query("UPDATE messages SET is_read=1 WHERE receiver_id=$userId");
}

// ── User profile data ─────────────────────────────────────
$userRow  = $conn->query("SELECT * FROM users WHERE id=$userId")->fetch_assoc();
$myApps   = $conn->query("SELECT COUNT(*) c FROM applications WHERE student_id=$userId")->fetch_assoc()['c'];
$approved = $conn->query("SELECT COUNT(*) c FROM applications WHERE student_id=$userId AND status='approved'")->fetch_assoc()['c'];
$pending  = $conn->query("SELECT COUNT(*) c FROM applications WHERE student_id=$userId AND status='pending'")->fetch_assoc()['c'];
$unreadMsg   = unreadMessages($conn, $userId);
$unreadNotif = unreadNotifications($conn, $userId);

// Search filter
$search = $conn->real_escape_string(trim($_GET['search'] ?? ''));
$domain = $conn->real_escape_string(trim($_GET['domain'] ?? ''));
$type   = $conn->real_escape_string(trim($_GET['type'] ?? ''));

$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Dashboard — InternConnect</title>
<link rel="stylesheet" href="styles.css?v=2">
</head>
<body class="user-dashboard">

<!-- ══ SIDEBAR ══ -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <span>InternConnect</span>
    <small>Student Portal</small>
  </div>
  <nav>
    <div class="nav-section">Explore</div>
    <a href="?tab=overview"        class="nav-item <?= $tab==='overview'        ?'active':'' ?>"><span class="icon">🏠</span> Overview</a>
    <a href="?tab=internships"     class="nav-item <?= $tab==='internships'     ?'active':'' ?>"><span class="icon">🔍</span> Browse Internships</a>

    <div class="nav-section">My Activity</div>
    <a href="?tab=my_applications" class="nav-item <?= $tab==='my_applications' ?'active':'' ?>"><span class="icon">📋</span> My Applications</a>
    <a href="?tab=documents"       class="nav-item <?= $tab==='documents'       ?'active':'' ?>"><span class="icon">📄</span> My Documents</a>

    <div class="nav-section">Communication</div>
    <a href="?tab=messages"        class="nav-item <?= $tab==='messages'        ?'active':'' ?>">
      <span class="icon">💬</span> Messages
      <?php if($unreadMsg>0): ?><span class="badge"><?= $unreadMsg ?></span><?php endif; ?>
    </a>
    <a href="?tab=notifications"   class="nav-item <?= $tab==='notifications'   ?'active':'' ?>">
      <span class="icon">🔔</span> Notifications
      <?php if($unreadNotif>0): ?><span class="badge"><?= $unreadNotif ?></span><?php endif; ?>
    </a>

    <div class="nav-section">Account</div>
    <a href="?tab=profile"         class="nav-item <?= $tab==='profile'         ?'active':'' ?>"><span class="icon">👤</span> My Profile</a>
  </nav>
  <div class="sidebar-footer">
    <div class="user-chip">
      <div class="avatar"><?= strtoupper(substr($userName,0,1)) ?></div>
      <div class="user-info">
        <strong><?= htmlspecialchars($userName) ?></strong>
        <small><?= htmlspecialchars($userRow['university'] ?? 'Student') ?></small>
      </div>
    </div>
    <a href="logout.php" class="logout-btn">⏻ Sign out</a>
  </div>
</aside>

<!-- ══ MAIN ══ -->
<main class="main">
  <div class="topbar">
    <h1><?= $tab==='internships' ? 'Browse Internships' : ($tab==='my_applications' ? 'My Applications' : ucfirst(str_replace('_',' ',$tab))) ?></h1>
    <div style="display:flex;gap:10px;align-items:center">
      <?php if($unreadMsg>0): ?>
        <a href="?tab=messages" class="btn btn-ghost btn-sm">💬 <?= $unreadMsg ?> new</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="page-content">
    <?php if($flash): ?><div class="flash <?= str_contains($flash,'must') || str_contains($flash,'already') ? 'error' : '' ?>">✓ <?= htmlspecialchars($flash) ?></div><?php endif; ?>

    <!-- ══════════ OVERVIEW ══════════ -->
    <?php if($tab === 'overview'): ?>
    <div class="stats-grid">
      <div class="stat-card"><div class="stat-label">Applications Sent</div><div class="stat-value accent"><?= $myApps ?></div></div>
      <div class="stat-card"><div class="stat-label">Approved</div><div class="stat-value success"><?= $approved ?></div></div>
      <div class="stat-card"><div class="stat-label">Pending Review</div><div class="stat-value warning"><?= $pending ?></div></div>
      <div class="stat-card"><div class="stat-label">Open Internships</div><div class="stat-value"><?= $conn->query("SELECT COUNT(*) c FROM internships WHERE status='open'")->fetch_assoc()['c'] ?></div></div>
    </div>

    <!-- Recent applications -->
    <div class="card">
      <div class="card-header"><h2>My Recent Applications</h2><a href="?tab=my_applications" class="btn btn-ghost btn-sm">View all →</a></div>
      <div class="card-body table-wrap">
        <?php
        $res = $conn->query("SELECT a.*, i.title, i.company FROM applications a JOIN internships i ON a.internship_id=i.id WHERE a.student_id=$userId ORDER BY a.applied_at DESC LIMIT 5");
        if($res->num_rows===0): ?><p style="color:var(--muted)">You haven't applied to any internships yet. <a href="?tab=internships" style="color:var(--accent)">Browse opportunities →</a></p>
        <?php else: ?>
        <table><thead><tr><th>Internship</th><th>Company</th><th>Applied</th><th>Status</th></tr></thead><tbody>
        <?php while($row=$res->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($row['title']) ?></td>
          <td><?= htmlspecialchars($row['company']) ?></td>
          <td><?= date('d M Y', strtotime($row['applied_at'])) ?></td>
          <td><span class="pill pill-<?= $row['status'] ?>"><?= $row['status'] ?></span></td>
        </tr>
        <?php endwhile; ?></tbody></table>
        <?php endif; ?>
      </div>
    </div>

    <!-- Latest internships -->
    <div class="card">
      <div class="card-header"><h2>New Opportunities</h2><a href="?tab=internships" class="btn btn-ghost btn-sm">See all →</a></div>
      <div class="card-body">
        <div class="int-grid">
        <?php
        $res = $conn->query("SELECT * FROM internships WHERE status='open' ORDER BY created_at DESC LIMIT 3");
        while($row=$res->fetch_assoc()): ?>
          <div class="int-card">
            <div class="int-card-company"><?= htmlspecialchars($row['company']) ?></div>
            <div class="int-card-title"><?= htmlspecialchars($row['title']) ?></div>
            <div class="int-card-meta">
              <span class="tag"><?= htmlspecialchars($row['type']) ?></span>
              <?php if($row['domain']): ?><span class="tag teal"><?= htmlspecialchars($row['domain']) ?></span><?php endif; ?>
              <?php if($row['stipend']): ?><span class="tag green"><?= htmlspecialchars($row['stipend']) ?></span><?php endif; ?>
            </div>
            <div class="int-card-footer">
              <?php if($row['deadline']): ?><span class="deadline">Deadline: <?= date('d M Y', strtotime($row['deadline'])) ?></span><?php endif; ?>
              <a href="?tab=apply&id=<?= $row['id'] ?>" class="btn btn-primary btn-sm">Apply →</a>
            </div>
          </div>
        <?php endwhile; ?>
        </div>
      </div>
    </div>

    <!-- ══════════ BROWSE INTERNSHIPS ══════════ -->
    <?php elseif($tab === 'internships'): ?>
    <form method="GET" action="">
      <input type="hidden" name="tab" value="internships">
      <div class="filter-bar">
        <input name="search" value="<?= htmlspecialchars($search) ?>" placeholder="🔍  Search by title, company...">
        <input name="domain" value="<?= htmlspecialchars($domain) ?>" placeholder="Domain (e.g. IT, Finance)">
        <select name="type">
          <option value="">All types</option>
          <option <?= $type==='On-site' ?'selected':'' ?>>On-site</option>
          <option <?= $type==='Remote'  ?'selected':'' ?>>Remote</option>
          <option <?= $type==='Hybrid'  ?'selected':'' ?>>Hybrid</option>
        </select>
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="?tab=internships" class="btn btn-ghost">Reset</a>
      </div>
    </form>

    <?php
    $where = "WHERE status='open'";
    if($search) $where .= " AND (title LIKE '%$search%' OR company LIKE '%$search%' OR description LIKE '%$search%')";
    if($domain) $where .= " AND domain LIKE '%$domain%'";
    if($type)   $where .= " AND type='$type'";
    $internships = $conn->query("SELECT * FROM internships $where ORDER BY created_at DESC");
    ?>

    <div class="int-grid">
    <?php if($internships->num_rows === 0): ?>
      <p style="color:var(--muted);grid-column:1/-1">No internships found matching your criteria.</p>
    <?php endif; ?>
    <?php while($row=$internships->fetch_assoc()):
      $alreadyApplied = $conn->query("SELECT id FROM applications WHERE internship_id={$row['id']} AND student_id=$userId")->num_rows > 0;
    ?>
      <div class="int-card">
        <div class="int-card-company"><?= htmlspecialchars($row['company']) ?></div>
        <div class="int-card-title"><?= htmlspecialchars($row['title']) ?></div>
        <div class="int-card-meta">
          <span class="tag"><?= htmlspecialchars($row['type']) ?></span>
          <?php if($row['domain']): ?><span class="tag teal"><?= htmlspecialchars($row['domain']) ?></span><?php endif; ?>
          <?php if($row['stipend']): ?><span class="tag green"><?= htmlspecialchars($row['stipend']) ?></span><?php endif; ?>
          <?php if($row['location']): ?><span class="tag">📍 <?= htmlspecialchars($row['location']) ?></span><?php endif; ?>
        </div>
        <div class="int-card-desc"><?= htmlspecialchars($row['description']) ?></div>
        <?php if($row['duration']): ?><div style="font-size:.78rem;color:var(--muted)">⏱ <?= htmlspecialchars($row['duration']) ?></div><?php endif; ?>
        <div class="int-card-footer">
          <?php if($row['deadline']): ?><span class="deadline">⏰ <?= date('d M Y', strtotime($row['deadline'])) ?></span><?php endif; ?>
          <?php if($alreadyApplied): ?>
            <span class="pill pill-approved">✓ Applied</span>
          <?php else: ?>
            <a href="?tab=apply&id=<?= $row['id'] ?>" class="btn btn-primary btn-sm">Apply →</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endwhile; ?>
    </div>

    <!-- ══════════ APPLY FORM ══════════ -->
    <?php elseif($tab === 'apply'): ?>
    <?php
    $intId  = (int)($_GET['id'] ?? 0);
    $intRow = $conn->query("SELECT * FROM internships WHERE id=$intId AND status='open'")->fetch_assoc();
    if(!$intRow): ?><div class="flash error">Internship not found or no longer accepting applications.</div>
    <?php else:
    $alreadyApplied = $conn->query("SELECT id FROM applications WHERE internship_id=$intId AND student_id=$userId")->num_rows > 0;
    ?>
    <?php if($alreadyApplied): ?>
      <div class="flash">You have already applied for this internship.</div>
      <a href="?tab=my_applications" class="btn btn-ghost">← Back to Applications</a>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:1fr 1.5fr;gap:20px">
      <!-- Internship detail -->
      <div class="card" style="align-self:start">
        <div class="card-header"><h2>Internship Details</h2></div>
        <div class="card-body">
          <div style="color:var(--accent2);font-weight:600;margin-bottom:4px"><?= htmlspecialchars($intRow['company']) ?></div>
          <div style="font-weight:700;font-size:1.1rem;margin-bottom:14px"><?= htmlspecialchars($intRow['title']) ?></div>
          <div style="font-size:.83rem;color:var(--muted);line-height:1.6;margin-bottom:14px"><?= nl2br(htmlspecialchars($intRow['description'])) ?></div>
          <?php if($intRow['requirements']): ?>
          <div style="background:var(--surface);border-radius:8px;padding:12px;font-size:.82rem">
            <strong style="color:var(--accent);display:block;margin-bottom:6px">Requirements</strong>
            <?= nl2br(htmlspecialchars($intRow['requirements'])) ?>
          </div>
          <?php endif; ?>
          <div style="margin-top:14px;display:flex;flex-direction:column;gap:6px;font-size:.82rem">
            <?php if($intRow['stipend']): ?><div>💰 <?= htmlspecialchars($intRow['stipend']) ?></div><?php endif; ?>
            <?php if($intRow['duration']): ?><div>⏱ <?= htmlspecialchars($intRow['duration']) ?></div><?php endif; ?>
            <?php if($intRow['deadline']): ?><div>⏰ Deadline: <?= date('d M Y', strtotime($intRow['deadline'])) ?></div><?php endif; ?>
            <?php if($intRow['location']): ?><div>📍 <?= htmlspecialchars($intRow['location']) ?></div><?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Application form -->
      <div class="card">
        <div class="card-header"><h2>Your Application</h2></div>
        <div class="card-body">
          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="apply">
            <input type="hidden" name="internship_id" value="<?= $intId ?>">

            <div class="form-group">
              <label>Cover Letter (optional but recommended)</label>
              <textarea name="cover_letter" rows="6" placeholder="Tell the company why you're a great fit for this role..."></textarea>
            </div>

            <div class="form-group">
              <label>Upload CV / Resume (PDF, DOC, DOCX — max 5MB)</label>
              <?php if($userRow['cv_path']): ?>
                <div style="font-size:.8rem;color:var(--success);margin-bottom:8px">✓ You have a CV on file — it will be used unless you upload a new one.</div>
              <?php endif; ?>
              <div class="upload-zone" onclick="document.getElementById('cv_upload').click()">
                <input type="file" name="cv" id="cv_upload" accept=".pdf,.doc,.docx" onchange="document.getElementById('fname').textContent=this.files[0]?.name||''">
                <div class="upload-icon">📄</div>
                <div class="upload-label">Click to select your CV <br><span id="fname" style="color:var(--accent)"></span></div>
              </div>
            </div>

            <div style="margin-top:20px;display:flex;gap:10px">
              <button type="submit" class="btn btn-primary">🚀 Submit Application</button>
              <a href="?tab=internships" class="btn btn-ghost">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php endif; endif; ?>

    <!-- ══════════ MY APPLICATIONS ══════════ -->
    <?php elseif($tab === 'my_applications'): ?>
    <div class="card">
      <div class="card-header"><h2>All My Applications</h2></div>
      <div class="card-body">
        <?php
        $res = $conn->query("SELECT a.*, i.title, i.company, i.location, i.type FROM applications a JOIN internships i ON a.internship_id=i.id WHERE a.student_id=$userId ORDER BY a.applied_at DESC");
        if($res->num_rows===0): ?><p style="color:var(--muted)">No applications yet. <a href="?tab=internships" style="color:var(--accent)">Browse opportunities →</a></p>
        <?php else: ?>
        <?php while($row=$res->fetch_assoc()):
          $steps = ['pending','reviewed','approved'];
          $cur   = array_search($row['status'], $steps);
          $isRej = $row['status'] === 'rejected';
        ?>
        <div class="card" style="margin-bottom:16px;border-color:var(--border)">
          <div class="card-body" style="padding:16px">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px">
              <div>
                <div style="font-weight:700;font-size:.95rem"><?= htmlspecialchars($row['title']) ?></div>
                <div style="color:var(--accent2);font-size:.82rem"><?= htmlspecialchars($row['company']) ?></div>
                <div style="font-size:.77rem;color:var(--muted);margin-top:4px">Applied <?= date('d M Y', strtotime($row['applied_at'])) ?></div>
              </div>
              <span class="pill pill-<?= $row['status'] ?>"><?= $row['status'] ?></span>
            </div>
            <!-- Timeline -->
            <?php if(!$isRej): ?>
            <div class="timeline" style="margin-top:14px">
              <?php foreach(['Submitted','Under Review','Approved'] as $i=>$label): ?>
              <div class="tl-step">
                <div class="tl-dot <?= $cur>=$i ? 'done' : '' ?> <?= $cur===$i ? 'active' : '' ?>"><?= $cur>=$i ? '✓' : ($i+1) ?></div>
                <div class="tl-label"><?= $label ?></div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php else: ?>
              <div style="margin-top:10px;font-size:.8rem;color:var(--danger)">✗ Application was not selected for this position.</div>
            <?php endif; ?>
          </div>
        </div>
        <?php endwhile; endif; ?>
      </div>
    </div>

    <!-- ══════════ DOCUMENTS ══════════ -->
    <?php elseif($tab === 'documents'): ?>
    <div class="card">
      <div class="card-header"><h2>Upload / Update Your CV</h2></div>
      <div class="card-body">
        <?php if($userRow['cv_path']): ?>
          <div style="background:var(--surface);border-radius:8px;padding:14px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between">
            <div>
              <div style="font-weight:600;margin-bottom:4px">📄 Current CV on file</div>
              <div style="font-size:.8rem;color:var(--muted)"><?= htmlspecialchars($userRow['cv_path']) ?></div>
            </div>
            <a href="uploads/<?= htmlspecialchars($userRow['cv_path']) ?>" target="_blank" class="btn btn-ghost btn-sm">View →</a>
          </div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="apply">
          <input type="hidden" name="internship_id" value="0">
          <div class="upload-zone" onclick="document.getElementById('cv2').click()" style="margin-bottom:16px">
            <input type="file" name="cv" id="cv2" accept=".pdf,.doc,.docx" onchange="document.getElementById('fname2').textContent=this.files[0]?.name||''">
            <div class="upload-icon">📤</div>
            <div class="upload-label">Click to upload new CV (PDF, DOC, DOCX)<br><span id="fname2" style="color:var(--accent)"></span></div>
          </div>
          <button type="submit" class="btn btn-primary">Save CV</button>
        </form>
        <!-- Upload history -->
        <div style="margin-top:28px">
          <h3 style="font-size:.9rem;margin-bottom:12px">Upload History</h3>
          <?php
          $uploads = $conn->query("SELECT * FROM uploads WHERE user_id=$userId ORDER BY uploaded_at DESC LIMIT 10");
          if($uploads->num_rows===0): ?><p style="color:var(--muted);font-size:.83rem">No uploads yet.</p>
          <?php else: ?>
          <table><thead><tr><th>File</th><th>Type</th><th>Uploaded</th><th></th></tr></thead><tbody>
          <?php while($u=$uploads->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($u['file_name']) ?></td>
              <td><span class="pill pill-reviewed"><?= $u['file_type'] ?></span></td>
              <td><?= date('d M Y H:i', strtotime($u['uploaded_at'])) ?></td>
              <td><a href="uploads/<?= htmlspecialchars($u['file_path']) ?>" target="_blank" class="btn btn-ghost btn-sm">View</a></td>
            </tr>
          <?php endwhile; ?></tbody></table>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ══════════ MESSAGES ══════════ -->
    <?php elseif($tab === 'messages'): ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
      <div>
        <div class="card">
          <div class="card-header"><h2>Inbox</h2></div>
          <div class="card-body">
            <div class="msg-list">
            <?php
            $msgs = $conn->query("SELECT m.*, u.name as sname FROM messages m JOIN users u ON m.sender_id=u.id WHERE m.receiver_id=$userId ORDER BY m.sent_at DESC");
            if($msgs->num_rows===0): ?><p style="color:var(--muted)">No messages yet.</p><?php endif; ?>
            <?php while($m=$msgs->fetch_assoc()): ?>
              <div class="msg-item <?= $m['is_read'] ? '' : 'unread' ?>">
                <div class="msg-meta"><span>From: <?= htmlspecialchars($m['sname']) ?></span><span><?= date('d M, H:i', strtotime($m['sent_at'])) ?></span></div>
                <div class="msg-subject"><?= htmlspecialchars($m['subject'] ?: '(no subject)') ?></div>
                <div class="msg-body"><?= nl2br(htmlspecialchars($m['body'])) ?></div>
                <div style="margin-top:8px">
                  <a href="?tab=compose&to=<?= $m['sender_id'] ?>&name=<?= urlencode($m['sname']) ?>" class="btn btn-ghost btn-sm">↩ Reply</a>
                </div>
              </div>
            <?php endwhile; ?>
            </div>
          </div>
        </div>
      </div>
      <div>
        <div class="card">
          <div class="card-header"><h2>Sent</h2></div>
          <div class="card-body">
            <div class="msg-list">
            <?php
            $sent = $conn->query("SELECT m.*, u.name as rname FROM messages m JOIN users u ON m.receiver_id=u.id WHERE m.sender_id=$userId ORDER BY m.sent_at DESC LIMIT 20");
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

    <!-- ══════════ COMPOSE ══════════ -->
    <?php elseif($tab === 'compose'): ?>
    <?php $toId = (int)($_GET['to'] ?? 0); $toName = htmlspecialchars($_GET['name'] ?? 'Admin'); ?>
    <div class="card" style="max-width:600px">
      <div class="card-header"><h2>Send a Message</h2></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="send_message">
          <input type="hidden" name="receiver_id" value="<?= $toId ?>">
          <div class="form-group"><label>To</label><input value="<?= $toName ?>" readonly style="opacity:.6"></div>
          <div class="form-group"><label>Subject</label><input name="subject" placeholder="e.g. Question about my application"></div>
          <div class="form-group"><label>Message *</label><textarea name="body" required rows="6"></textarea></div>
          <div style="display:flex;gap:10px;margin-top:6px">
            <button type="submit" class="btn btn-primary">📤 Send</button>
            <a href="?tab=messages" class="btn btn-ghost">Cancel</a>
          </div>
        </form>
      </div>
    </div>

    <!-- ══════════ NOTIFICATIONS ══════════ -->
    <?php elseif($tab === 'notifications'): ?>
    <div class="card">
      <div class="card-header">
        <h2>Notifications</h2>
        <a href="?mark_read=1" class="btn btn-ghost btn-sm">Mark all read</a>
      </div>
      <div class="card-body">
        <?php
        $notifs = $conn->query("SELECT * FROM notifications WHERE user_id=$userId ORDER BY created_at DESC LIMIT 50");
        if($notifs->num_rows===0): ?><p style="color:var(--muted)">No notifications yet.</p><?php endif; ?>
        <?php while($n=$notifs->fetch_assoc()): ?>
        <div class="notif-item">
          <div class="notif-dot <?= $n['is_read'] ? 'read' : $n['type'] ?>"></div>
          <div>
            <div class="notif-text"><?= htmlspecialchars($n['message']) ?></div>
            <div class="notif-time"><?= date('d M Y, H:i', strtotime($n['created_at'])) ?></div>
          </div>
        </div>
        <?php endwhile; ?>
      </div>
    </div>

    <!-- ══════════ PROFILE ══════════ -->
    <?php elseif($tab === 'profile'): ?>
    <div class="card" style="max-width:640px">
      <div class="card-header"><h2>My Profile</h2></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="update_profile">
          <div class="form-grid">
            <div class="form-group full">
              <label>Full Name</label>
              <input value="<?= htmlspecialchars($userRow['name']) ?>" readonly style="opacity:.6">
            </div>
            <div class="form-group full">
              <label>Email</label>
              <input value="<?= htmlspecialchars($userRow['email']) ?>" readonly style="opacity:.6">
            </div>
            <div class="form-group">
              <label>Phone Number</label>
              <input name="phone" value="<?= htmlspecialchars($userRow['phone'] ?? '') ?>" placeholder="+237 6XX XXX XXX">
            </div>
            <div class="form-group">
              <label>University / School</label>
              <input name="university" value="<?= htmlspecialchars($userRow['university'] ?? '') ?>" placeholder="e.g. Siantou University Institute">
            </div>
            <div class="form-group">
              <label>Department</label>
              <input name="department" value="<?= htmlspecialchars($userRow['department'] ?? '') ?>" placeholder="e.g. Software Engineering">
            </div>
            <div class="form-group">
              <label>Level / Year</label>
              <input name="level" value="<?= htmlspecialchars($userRow['level'] ?? '') ?>" placeholder="e.g. HND Year 2">
            </div>
          </div>
          <div style="margin-top:20px">
            <button type="submit" class="btn btn-primary">💾 Save Profile</button>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

  </div>
</main>

<script>
// Poll for new messages/notifications every 30 seconds
setInterval(() => {
  fetch('ajax_poll.php?type=counts&user=<?= $userId ?>')
    .then(r => r.json())
    .then(d => {
      // Update badge counts without reloading page
      if(d.messages > 0) {
        document.title = '(' + d.messages + ') InternConnect';
      }
    }).catch(() => {});
}, 30000);
</script>
</body>
</html>
