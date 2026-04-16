---
title: "Generosity Driven Healthcare"
date: 2018-09-23
url: donate
aliases:
  - give
smallCover: true
titleOnCover: true
titlePush: right
---

<style>
.oemr-donate-wrap { max-width: 720px; margin: 0 auto; }

.oemr-donate-intro { font-size: 1.05rem; line-height: 1.75; margin-bottom: .75rem; }

.oemr-tax-badge {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 5px 13px; border-radius: 99px;
  background: #e8f5ee; color: #1a7a45;
  font-size: .8rem; font-weight: 600;
  margin-bottom: 2rem;
}

.oemr-section-label {
  font-size: .7rem; font-weight: 700; letter-spacing: .1em;
  text-transform: uppercase; color: #999;
  margin: 0 0 .875rem; border: none; padding: 0;
}
.oemr-section-label::after { display: none; }

.oemr-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 12px; margin-bottom: 2rem;
}
.oemr-card {
  border: 1px solid #e2e4e8; border-radius: 12px;
  padding: 1.125rem 1.25rem 1.25rem;
  display: flex; flex-direction: column; gap: 5px;
  background: #fff;
  transition: border-color .15s, box-shadow .15s;
}
.oemr-card:hover { border-color: #bcc0c8; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
.oemr-card-icon {
  width: 36px; height: 36px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 6px; font-weight: 700; font-size: 15px;
}
.oemr-card-icon.ic-zf  { background: #00C47C; color: #fff; }
.oemr-card-icon.ic-gh  { background: #f0f0f0; }
.oemr-card-icon.ic-oc  { background: #1F4BFF; color: #fff; font-size: 20px; line-height: 1; }
.oemr-card h3 { font-size: .95rem; font-weight: 700; margin: 0; }
.oemr-card p  { font-size: .875rem; color: #555; line-height: 1.55; margin: 0; flex: 1; }
.oemr-pill {
  display: inline-block; font-size: .7rem; font-weight: 600;
  padding: 2px 8px; border-radius: 99px;
  background: #e8f5ee; color: #1a7a45; margin-bottom: 2px;
}

.oemr-donate-btn {
  display: inline-flex; align-items: center;
  margin-top: .875rem; padding: 9px 18px; border-radius: 8px;
  font-size: .875rem; font-weight: 600; text-decoration: none !important;
  transition: opacity .15s, transform .1s; align-self: flex-start;
  
}
.oemr-donate-btn:hover  { opacity: .87; transform: translateY(-1px); text-decoration: none !important; }
.oemr-donate-btn:active { transform: none; }
.oemr-donate-btn--zf { background: #00C47C; color: #fff !important; }
.oemr-donate-btn--gh { background: #24292f; color: #fff !important; }
.oemr-donate-btn--oc { background: #1F4BFF; color: #fff !important; }

.oemr-crypto-grid { display: grid; gap: 10px; margin-bottom: 2rem; }
.oemr-crypto-card {
  border: 1px solid #e2e4e8; border-radius: 12px;
  padding: 1rem 1.25rem; background: #fafafa;
}
.oemr-crypto-header { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.oemr-crypto-dot { width: 11px; height: 11px; border-radius: 50%; flex-shrink: 0; }
.oemr-crypto-dot.btc { background: #F7931A; }
.oemr-crypto-dot.eth { background: #627EEA; }
.oemr-crypto-card strong { font-size: .9rem; }
.oemr-crypto-addr {
  font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
  font-size: .78rem; color: #444; word-break: break-all;
  line-height: 1.6; margin: 0; user-select: all;
}
.oemr-copy-btn {
  margin-top: 8px; background: none; border: 1px solid #d1d5db;
  border-radius: 6px; padding: 4px 10px; font-size: .75rem;
  color: #555; cursor: pointer; display: inline-flex;
  align-items: center; gap: 5px; transition: background .1s;
}
.oemr-copy-btn:hover { background: #f3f4f6; }
.oemr-donate-footer {
  font-size: .82rem; color: #888; border-top: 1px solid #e5e7eb;
  padding-top: 1.25rem; line-height: 1.7;
}
</style>

<div class="oemr-donate-wrap">

<p class="oemr-donate-intro">OpenEMR depends on donations from users, developers, and philanthropists to continue moving the project forward. Donations go to the OpenEMR Foundation, a 501(c)(3) nonprofit organization that exists to support the OpenEMR project.</p>

<span class="oemr-tax-badge">
  <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M8 2l1.5 4h4.5l-3.5 2.5 1.5 4.5L8 10.5l-4 2.5 1.5-4.5L2 6h4.5z"/></svg>
  501(c)(3) — donations are tax-deductible
</span>

<p class="oemr-section-label">Online platforms</p>
<div class="oemr-cards">

  <div class="oemr-card">
    <div class="oemr-card-icon ic-zf">Z</div>
    <h3>Zeffy</h3>
    <span class="oemr-pill">0% platform fees</span>
    <p>One-time or monthly donations. 100% of every dollar goes to OpenEMR — Zeffy covers its own costs separately. Tax receipt included.</p>
    <a href="https://www.zeffy.com/en-US/donation-form/support-openemr"
       target="_blank" rel="noopener"
       class="oemr-donate-btn oemr-donate-btn--zf">Donate via Zeffy</a>
  </div>

  <div class="oemr-card">
    <div class="oemr-card-icon ic-gh">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="#24292f" aria-hidden="true"><path d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.166 6.839 9.489.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.604-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.462-1.11-1.462-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.578 9.578 0 0 1 12 6.836a9.59 9.59 0 0 1 2.504.337c1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.744 0 .267.18.578.688.48C19.138 20.163 22 16.418 22 12c0-5.523-4.477-10-10-10z"/></svg>
    </div>
    <h3>GitHub Sponsors</h3>
    <p>Sponsor OpenEMR through GitHub. A natural fit for developers and teams already on the platform.</p>
    {{< github_sponsor >}}
  </div>

  <div class="oemr-card">
    <div class="oemr-card-icon ic-oc">○</div>
    <h3>Open Collective</h3>
    <p>Full financial transparency. See exactly how funds are received and spent by the Foundation.</p>
    {{< open_collective >}}
  </div>

</div>

<p class="oemr-section-label">Cryptocurrency</p>
<div class="oemr-crypto-grid">

  <div class="oemr-crypto-card">
    <div class="oemr-crypto-header">
      <span class="oemr-crypto-dot btc"></span>
      <strong>Bitcoin (BTC)</strong>
    </div>
    <p class="oemr-crypto-addr" id="oemr-btc">3GCNLbiZmHP26fA77NkLgY4tKdEts3ADQg</p>
    <button class="oemr-copy-btn" onclick="oemrCopy('oemr-btc',this)">
      <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="5" y="5" width="9" height="9" rx="1.5"/><path d="M11 5V3.5A1.5 1.5 0 0 0 9.5 2h-6A1.5 1.5 0 0 0 2 3.5v6A1.5 1.5 0 0 0 3.5 11H5"/></svg>
      Copy address
    </button>
  </div>

  <div class="oemr-crypto-card">
    <div class="oemr-crypto-header">
      <span class="oemr-crypto-dot eth"></span>
      <strong>Ethereum &amp; ERC-20 tokens (ETH)</strong>
    </div>
    <p class="oemr-crypto-addr" id="oemr-eth">0xcD7542b2DcF41072aC7783C7cbc31B0f1E257E80</p>
    <button class="oemr-copy-btn" onclick="oemrCopy('oemr-eth',this)">
      <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="5" y="5" width="9" height="9" rx="1.5"/><path d="M11 5V3.5A1.5 1.5 0 0 0 9.5 2h-6A1.5 1.5 0 0 0 2 3.5v6A1.5 1.5 0 0 0 3.5 11H5"/></svg>
      Copy address
    </button>
  </div>

</div>

<p class="oemr-donate-footer">Donations go to the OpenEMR Foundation, Inc., a 501(c)(3) nonprofit organization that exists to support the OpenEMR open-source project.</p>

</div>

<script>
function oemrCopy(id, btn) {
  var txt = document.getElementById(id).textContent.trim();
  navigator.clipboard.writeText(txt).then(function() {
    btn.textContent = 'Copied!';
    setTimeout(function() { btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="5" y="5" width="9" height="9" rx="1.5"/><path d="M11 5V3.5A1.5 1.5 0 0 0 9.5 2h-6A1.5 1.5 0 0 0 2 3.5v6A1.5 1.5 0 0 0 3.5 11H5"/></svg> Copy address'; }, 2000);
  });
}
</script>
