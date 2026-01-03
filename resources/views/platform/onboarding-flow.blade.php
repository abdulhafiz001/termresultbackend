<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>TermResult - Modern Onboarding</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #4f46e5;
      --primary-light: #e0e7ff;
      --slate-900: #0f172a;
      --slate-800: #1e293b;
      --slate-600: #475569;
      --slate-400: #94a3b8;
      --slate-50: #f8fafc;
      --white: #ffffff;
      --accent-cyan: #06b6d4;
      --accent-green: #10b981;
      --accent-purple: #8b5cf6;
    }

    @page { margin: 10mm; size: A4; }
    
    * { box-sizing: border-box; -webkit-print-color-adjust: exact; }
    
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: var(--slate-800);
      font-size: 12px;
      line-height: 1.6;
      background-color: #f1f5f9;
      margin: 0;
      padding: 20px;
      position: relative;
      overflow-x: hidden;
    }

    /* Abstract UI Decorations */
    body::before {
      content: "";
      position: absolute;
      top: -100px;
      right: -100px;
      width: 400px;
      height: 400px;
      background: radial-gradient(circle, rgba(79, 70, 229, 0.08) 0%, rgba(255, 255, 255, 0) 70%);
      z-index: -1;
    }

    .page-container {
      max-width: 850px;
      margin: 0 auto;
      background: var(--white);
      border-radius: 24px;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
      border: 1px solid rgba(255,255,255,0.7);
      overflow: hidden;
      position: relative;
    }

    /* Top Navigation Style Bar */
    .topbar {
      padding: 30px 40px;
      background: var(--slate-900);
      color: var(--white);
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: relative;
      overflow: hidden;
    }

    .topbar::after {
      content: "";
      position: absolute;
      bottom: -50px;
      right: -50px;
      width: 150px;
      height: 150px;
      background: var(--primary);
      filter: blur(80px);
      opacity: 0.4;
    }

    .brand-group h1 {
      margin: 0;
      font-size: 22px;
      font-weight: 800;
      letter-spacing: -0.5px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .brand-group h1 span {
      color: var(--primary-light);
      font-weight: 400;
    }

    .brand-tagline {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      color: var(--slate-400);
      font-weight: 600;
      margin-top: 4px;
    }

    .meta-info {
      text-align: right;
      font-size: 10px;
      color: var(--slate-400);
    }

    /* Header Content */
    .header-content {
      padding: 40px 40px 20px 40px;
    }

    .main-title {
      font-size: 24px;
      font-weight: 800;
      color: var(--slate-900);
      letter-spacing: -0.5px;
      margin-bottom: 12px;
    }

    .main-subtitle {
      font-size: 13px;
      color: var(--slate-600);
      max-width: 600px;
    }

    /* Main Grid Layout */
    .content-grid {
      display: grid;
      grid-template-columns: 1.6fr 1fr;
      gap: 40px;
      padding: 20px 40px 40px 40px;
    }

    /* Steps Timeline */
    .timeline {
      position: relative;
    }

    .step-card {
      position: relative;
      padding-left: 50px;
      margin-bottom: 30px;
    }

    /* The Vertical Line */
    .step-card::before {
      content: "";
      position: absolute;
      left: 17px;
      top: 40px;
      bottom: -40px;
      width: 2px;
      background: var(--slate-50);
      z-index: 1;
    }

    .step-card:last-child::before { display: none; }

    .step-number {
      position: absolute;
      left: 0;
      top: 0;
      width: 36px;
      height: 36px;
      background: var(--primary);
      color: white;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 14px;
      z-index: 2;
      box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2);
    }

    .step-content {
      background: var(--slate-50);
      padding: 18px;
      border-radius: 16px;
      border: 1px solid #edf2f7;
      transition: transform 0.2s ease;
    }

    .step-title {
      font-size: 14px;
      font-weight: 700;
      color: var(--slate-900);
      margin-bottom: 4px;
    }

    .step-desc {
      font-size: 11px;
      color: var(--slate-600);
      line-height: 1.5;
    }

    .step-tip {
      margin-top: 10px;
      padding-top: 10px;
      border-top: 1px dashed #cbd5e1;
      font-size: 10px;
      font-style: italic;
      color: var(--primary);
      display: flex;
      align-items: center;
      gap: 5px;
    }

    /* Sidebar Widgets */
    .sidebar {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .widget {
      padding: 24px;
      border-radius: 20px;
      position: relative;
      overflow: hidden;
    }

    .widget-dark {
      background: var(--slate-900);
      color: var(--white);
    }

    .widget-light {
      background: #f0f4ff;
      border: 1px solid var(--primary-light);
    }

    .widget h3 {
      margin: 0 0 10px 0;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--primary-light);
    }

    .widget p {
      margin: 0;
      font-size: 11px;
      line-height: 1.6;
      opacity: 0.9;
    }

    .contact-card {
      background: var(--white);
      border: 1px solid #e2e8f0;
      border-radius: 20px;
      padding: 20px;
    }

    .contact-item {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
      font-size: 11px;
    }

    .contact-item span:first-child { color: var(--slate-400); }
    .contact-item span:last-child { font-weight: 600; color: var(--slate-800); }

    .footer {
      padding: 20px;
      text-align: center;
      border-top: 1px solid var(--slate-50);
      font-size: 10px;
      color: var(--slate-400);
      letter-spacing: 0.5px;
    }

    /* Accent Colors for Steps */
    .bg-cyan { background: var(--accent-cyan); box-shadow: 0 4px 10px rgba(6, 182, 212, 0.2); }
    .bg-green { background: var(--accent-green); box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2); }
    .bg-purple { background: var(--accent-purple); box-shadow: 0 4px 10px rgba(139, 92, 246, 0.2); }
    .bg-orange { background: #f59e0b; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.2); }
    .bg-red { background: #ef4444; box-shadow: 0 4px 10px rgba(239, 68, 68, 0.2); }
    .bg-dark { background: var(--slate-900); box-shadow: 0 4px 10px rgba(15, 23, 42, 0.2); }

  </style>
</head>
<body>

<div class="page-container">
  <div class="topbar">
    <div class="brand-group">
      <h1>Term<span>Result</span></h1>
      <div class="brand-tagline">Onboarding Blueprint</div>
    </div>
    <div class="meta-info">
      <div>REF: TR-ONBOARD-2024</div>
      <div>DATED: {{ ($generatedAt ?? now())->format('d M Y') }}</div>
    </div>
  </div>

  <div class="header-content">
    <div class="main-title">Launch your school portal</div>
    <div class="main-subtitle">Follow this sequence to ensure your academic data, financial records, and student report cards are generated accurately and securely.</div>
  </div>

  <div class="content-grid">
    <div class="timeline">
      
      <div class="step-card">
        <div class="step-number">1</div>
        <div class="step-content">
          <div class="step-title">Academic Setup</div>
          <div class="step-desc">Initialize your sessions and terms. Define the active timeline for the current school year.</div>
          <div class="step-tip">💡 Pro-tip: Set your "Current Term" to automate dashboard stats.</div>
        </div>
      </div>

      <div class="step-card">
        <div class="step-number bg-cyan">2</div>
        <div class="step-content">
          <div class="step-title">Identity & Branding</div>
          <div class="step-desc">Upload high-res logos and set school themes. This applies to all generated PDF report cards.</div>
        </div>
      </div>

      <div class="step-card">
        <div class="step-number bg-green">3</div>
        <div class="step-content">
          <div class="step-title">Grading Framework</div>
          <div class="step-desc">Configure A-F ranges and remarks. You can assign different systems to Nursery, Primary, or Secondary sections.</div>
        </div>
      </div>

      <div class="step-card">
        <div class="step-number bg-orange">4</div>
        <div class="step-content">
          <div class="step-title">Structure Classes</div>
          <div class="step-desc">Add your arms (e.g., JSS 1A, JSS 1B) and subjects. Link subjects to specific classes to simplify teacher entry.</div>
        </div>
      </div>

      <div class="step-card">
        <div class="step-number bg-purple">5</div>
        <div class="step-content">
          <div class="step-title">Staff Onboarding</div>
          <div class="step-desc">Create teacher portals and assign them to their respective subjects and form classes.</div>
        </div>
      </div>

      <div class="step-card">
        <div class="step-number bg-red">6</div>
        <div class="step-content">
          <div class="step-title">Student Enrollment</div>
          <div class="step-desc">Import student data via Excel or manual entry. Form teachers can then assign subjects to individual students.</div>
        </div>
      </div>

      <div class="step-card">
        <div class="step-number bg-dark">7</div>
        <div class="step-content">
          <div class="step-title">Go Live & Management</div>
          <div class="step-desc">Begin recording scores, managing fee payments, and tracking school performance from your admin dashboard.</div>
        </div>
      </div>

    </div>

    <div class="sidebar">
      <div class="widget widget-dark">
        <h3>Expected Outcome</h3>
        <p>A fully synchronized system where financial records match student enrollment, and report cards are generated with zero manual calculation errors.</p>
      </div>

      <div class="widget widget-light">
        <h3 style="color: var(--primary);">Best Practice</h3>
        <p style="color: var(--slate-800);">Always verify subject totals (e.g., 100%) before teachers begin entering CA scores to prevent recalculation issues later.</p>
      </div>

      <div class="contact-card">
        <h3 style="margin-top:0; font-size:12px; color:var(--slate-900);">Support Channels</h3>
        <div class="contact-item">
          <span>WhatsApp</span>
          <span>09044426264</span>
        </div>
        <div class="contact-item">
          <span>Email</span>
          <span>support@termresult.com</span>
        </div>
        <div class="contact-item">
          <span>Web</span>
          <span>www.termresult.com</span>
        </div>
      </div>
    </div>
  </div>

  <div class="footer">
    TermResult • The Modern Standard for School Management • Built for Excellence
  </div>
</div>

</body>
</html>