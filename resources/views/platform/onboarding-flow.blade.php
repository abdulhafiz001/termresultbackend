<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>TermResult - School Onboarding Flow</title>
  <style>
    @page { margin: 16mm; size: A4; }
    * { box-sizing: border-box; }
    body {
      font-family: DejaVu Sans, sans-serif;
      color: #0f172a;
      font-size: 11px;
      line-height: 1.45;
    }
    .topbar {
      padding: 14px 16px;
      border-radius: 10px;
      background: #0b1220;
      color: #fff;
    }
    .brand {
      font-size: 18px;
      font-weight: 800;
      letter-spacing: 0.5px;
    }
    .brand small {
      display: block;
      font-size: 10px;
      font-weight: 600;
      color: #c7d2fe;
      margin-top: 2px;
      letter-spacing: 1px;
      text-transform: uppercase;
    }
    .meta {
      margin-top: 8px;
      font-size: 9px;
      color: #e2e8f0;
    }
    .meta span { display: inline-block; margin-right: 12px; }

    .card {
      margin-top: 14px;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 14px 14px;
      background: #ffffff;
    }
    .title {
      font-size: 14px;
      font-weight: 800;
      margin-bottom: 6px;
    }
    .subtitle {
      font-size: 10px;
      color: #475569;
      margin-bottom: 12px;
    }
    .grid { display: table; width: 100%; }
    .col { display: table-cell; vertical-align: top; }
    .col.left { width: 62%; padding-right: 10px; }
    .col.right { width: 38%; padding-left: 10px; }

    .step {
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 10px 12px;
      margin-bottom: 10px;
      background: #f8fafc;
    }
    .step-head {
      display: table;
      width: 100%;
      margin-bottom: 6px;
    }
    .badge {
      display: table-cell;
      width: 40px;
      height: 40px;
      border-radius: 10px;
      text-align: center;
      vertical-align: middle;
      font-weight: 900;
      color: #fff;
      background: #4f46e5;
    }
    .step-title {
      display: table-cell;
      vertical-align: middle;
      padding-left: 10px;
      font-weight: 900;
      font-size: 12px;
      color: #111827;
    }
    .step-desc {
      font-size: 10px;
      color: #334155;
    }
    .hint {
      margin-top: 6px;
      font-size: 9px;
      color: #64748b;
    }
    .kpi {
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 12px;
      background: #0b1220;
      color: #fff;
      margin-bottom: 10px;
    }
    .kpi h3 {
      margin: 0 0 6px 0;
      font-size: 11px;
      letter-spacing: 1px;
      text-transform: uppercase;
      color: #c7d2fe;
    }
    .kpi p { margin: 0; font-size: 10px; color: #e2e8f0; }
    .contact {
      border: 1px dashed #c7d2fe;
      border-radius: 12px;
      padding: 12px;
      background: #eef2ff;
    }
    .contact strong { color: #1e293b; }
    .footer {
      margin-top: 14px;
      font-size: 9px;
      color: #64748b;
      text-align: center;
      border-top: 1px solid #e2e8f0;
      padding-top: 10px;
    }
  </style>
</head>
<body>
  <div class="topbar">
    <div class="brand">
      TermResult
      <small>School Onboarding Flow</small>
    </div>
    <div class="meta">
      <span>Document: TR-ONBOARD-001</span>
      <span>Generated: {{ ($generatedAt ?? now())->format('d M Y, h:ia') }}</span>
    </div>
  </div>

  <div class="card">
    <div class="title">How schools get started (practical, step-by-step)</div>
    <div class="subtitle">
      Share this with a school admin after approval. It shows the correct order to set up TermResult so results, fees, and dashboards work smoothly.
    </div>

    <div class="grid">
      <div class="col left">
        <div class="step">
          <div class="step-head">
            <div class="badge">1</div>
            <div class="step-title">Academic Setup (Sessions & Terms)</div>
          </div>
          <div class="step-desc">
            Create the academic session, set current session, and configure terms (First, Second, Third).
          </div>
          <div class="hint">Tip: Always ensure the current session + current term are set correctly.</div>
        </div>

        <div class="step">
          <div class="step-head">
            <div class="badge" style="background:#06b6d4;">2</div>
            <div class="step-title">School Configuration</div>
          </div>
          <div class="step-desc">
            Upload logo, set school colors/branding, enable/disable features, and configure result position display rules.
          </div>
        </div>

        <div class="step">
          <div class="step-head">
            <div class="badge" style="background:#22c55e;">3</div>
            <div class="step-title">Grading Configuration (Per Class)</div>
          </div>
          <div class="step-desc">
            Create grading systems (A–F ranges) and assign them to the correct classes. This controls grade/remark calculations.
          </div>
        </div>

        <div class="step">
          <div class="step-head">
            <div class="badge" style="background:#f59e0b;">4</div>
            <div class="step-title">Add Classes & Subjects</div>
          </div>
          <div class="step-desc">
            Create classes (e.g., JSS1–SS3) and add all school subjects. Assign subjects to classes.
          </div>
        </div>

        <div class="step">
          <div class="step-head">
            <div class="badge" style="background:#a855f7;">5</div>
            <div class="step-title">Add Teachers</div>
          </div>
          <div class="step-desc">
            Create teacher accounts, assign teaching classes/subjects, and (optionally) set form teachers for classes.
          </div>
        </div>

        <div class="step">
          <div class="step-head">
            <div class="badge" style="background:#ef4444;">6</div>
            <div class="step-title">Add Students (Teacher workflow)</div>
          </div>
          <div class="step-desc">
            Teachers (especially form teachers) can add/import students for their form class, then select subjects each student offers.
          </div>
        </div>

        <div class="step" style="margin-bottom:0;">
          <div class="step-head">
            <div class="badge" style="background:#0b1220;">7</div>
            <div class="step-title">Start Managing Results & School Activities</div>
          </div>
          <div class="step-desc">
            Teachers enter scores and manage exams/assignments (if enabled). Admins review results, run promotions (3rd term only),
            manage fees/payments, announcements, complaints, and view reports.
          </div>
        </div>
      </div>

      <div class="col right">
        <div class="kpi">
          <h3>Outcome</h3>
          <p>
            When setup is done in this order, the school can safely record scores, generate report cards, and give students access to verified PDFs.
          </p>
        </div>

        <div class="kpi">
          <h3>Best Practice</h3>
          <p>
            Only enable Print/Download for report cards when all subject scores have been recorded for a student.
          </p>
        </div>

        <div class="contact">
          <div style="font-weight:900; font-size:11px; margin-bottom:6px;">Need help onboarding?</div>
          <div style="font-size:10px;">
            Email: <strong>support@termresult.com</strong><br/>
            WhatsApp: <strong>09044426264</strong><br/>
            Web: <strong>termresult.com</strong>
          </div>
        </div>
      </div>
    </div>

    <div class="footer">
      TermResult • Modern School Management System • Secure • Verified • Nigeria-focused
    </div>
  </div>
</body>
</html>


