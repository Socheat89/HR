<?php
session_start();
// Simple UI page for posting/uploading videos to a Facebook Page.
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Post to Facebook Page</title>
</head>
<style>
    :root{
  --bg:#f6f7fb;
  --card:#ffffff;
  --muted:#9aa0a6;
  --accent:#0f1724;
  --primary:#1f2937;
  --brand:#111827;
  --button:#23292f;
}
*{box-sizing:border-box;font-family:Inter, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial}
body{margin:0;background:var(--bg);color:#111}
.topbar{padding:18px 24px;display:flex;justify-content:flex-end}
.btn{border:0;padding:10px 18px;border-radius:8px;background:#eee;cursor:pointer}
.add-account{background:transparent;border:1px dashed #ddd;padding:12px 20px;border-radius:10px}
.container{max-width:420px;margin:8px auto;padding:12px}
.card{background:var(--card);border-radius:12px;padding:18px;margin-bottom:14px;box-shadow:0 6px 18px rgba(16,24,40,0.05)}
.upload-card{text-align:center}
.file-btn{display:inline-block;padding:10px 22px;border-radius:30px;border:1px solid #ccc;background:white}
#videoFile{display:none}
.or{margin:12px 0;color:var(--muted)}
.input{width:100%;padding:12px;border-radius:8px;border:1px solid #e6e6e6;margin-top:8px}
.url-input{padding-left:14px}
.options{display:flex;gap:12px;align-items:center;justify-content:center;margin:12px 0}
.radio{font-size:14px;color:var(--muted)}
.upload-primary{margin-top:10px;background:var(--button);color:white;padding:12px 18px;width:100%;font-weight:600;border-radius:8px}
.status{margin-top:10px;color:var(--muted);font-size:14px}
.info-card .info-header{display:flex;align-items:center;gap:10px}
.info-header h3{margin:0;color:#fff;background:var(--accent);padding:10px;border-radius:8px}
.back{background:transparent;border:0;font-size:18px}
.textarea{width:100%;min-height:120px;padding:12px;border-radius:8px;border:1px solid #eee;margin-top:8px}
.preview-row{display:flex;gap:12px;margin-top:12px}
.preview-box{flex:1;background:#f3f4f6;padding:18px;border-radius:8px;color:var(--muted)}
.post-btn{width:100%;margin-top:14px;background:var(--button);color:white;padding:12px;border-radius:8px;font-weight:600}

.info-card { display: none; }

@media (max-width:460px){.container{padding:10px}}

</style>

<body>
  <div class="page">
    <header class="topbar">
      <button class="btn add-account" onclick="window.location.href='fb_login.php'">+ Add Account</button>
    </header>

    <main class="container">
      <section class="card upload-card">
        <div class="file-select">
          <label class="btn file-btn" for="videoFile">Select Video File</label>
          <input id="videoFile" name="videoFile" type="file" accept="video/*" />
        </div>

        <div class="or">OR</div>

        <input id="videoUrl" class="input url-input" placeholder="URL of Facebook, Youtube, Tiktok..." />

        <div class="options">
          <label class="radio"><input type="radio" name="opt" value="1" checked> Option 1</label>
          <label class="radio"><input type="radio" name="opt" value="2"> Option 2</label>
        </div>

        <button id="uploadBtn" class="btn upload-primary">UPLOAD VIDEO</button>
        <div id="status" class="status"></div>
      </section>

      <section class="card info-card">
        <div class="info-header">
          <button class="back">←</button>
          <h3>Video Information</h3>
        </div>

        <div class="form-row">
          <select id="pageSelect" class="input">
            <option value="">Choose Pages</option>
            <?php if (isset($_SESSION['pages'])) { foreach ($_SESSION['pages'] as $page) { ?>
            <option value="<?php echo htmlspecialchars($page['id']); ?>"><?php echo htmlspecialchars($page['name']); ?></option>
            <?php } } ?>
          </select>
        </div>

        <label class="label">Write caption here</label>
        <textarea id="caption" class="textarea" placeholder="Write caption here"></textarea>

        <div class="preview-row">
          <div class="preview-box">Description</div>
          <div class="preview-box">Page Name</div>
        </div>

        <button id="postBtn" class="btn post-btn">POST</button>
      </section>
    </main>
  </div>

  <script>
    const pages = <?php echo json_encode($_SESSION['pages'] ?? []); ?>;
  </script>

  <script>
    const fileInput = document.getElementById('videoFile');
    const uploadBtn = document.getElementById('uploadBtn');
    const status = document.getElementById('status');

    fileInput.style.display = 'none';
    document.querySelector('.file-btn').addEventListener('click', (e) => {
      fileInput.click();
    });

    fileInput.addEventListener('change', () => {
      const f = fileInput.files[0];
      status.textContent = f ? `Selected: ${f.name}` : '';
    });

    uploadBtn.addEventListener('click', async () => {
      status.textContent = '';
      const file = fileInput.files[0];
      const url = document.getElementById('videoUrl').value.trim();
      const pageId = document.getElementById('pageSelect').value;
      const caption = document.getElementById('caption').value.trim();

      if (!pageId) {
        status.textContent = 'Please choose a Page from the dropdown.';
        return;
      }

      if (!file && !url) {
        status.textContent = 'Please select a video file or paste a URL.';
        return;
      }

      status.textContent = 'Uploading...';

      try {
        const form = new FormData();
        form.append('page_id', pageId);
        form.append('description', caption);
        if (file) form.append('video', file);
        form.append('video_url', url);

        const selectedPage = pages.find(p => p.id == pageId);
        if (selectedPage) {
          form.append('page_access_token', selectedPage.access_token);
        }

        const res = await fetch('upload_video_web.php', {
          method: 'POST',
          body: form
        });
        const data = await res.json();
        if (data.error) {
          status.textContent = 'Error: ' + (data.error.message || JSON.stringify(data.error));
        } else {
          status.textContent = 'Success: ' + (data.id || JSON.stringify(data));
          document.querySelector('.info-card').style.display = 'block';
        }
      } catch (err) {
        status.textContent = 'Upload failed: ' + err.message;
      }
    });
  </script>
</body>
</html>
