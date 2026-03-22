<?php
$defaultHtml = <<<'HTML'
<div class="demo-box">
  <h1>Hallo Welt</h1>
  <p>Diesen Inhalt kannst du links im HTML-Editor oder direkt rechts in der Vorschau bearbeiten.</p>
  <button id="demoButton">Test Button</button>
</div>
HTML;

$defaultCss = <<<'CSS'
body {
  font-family: Arial, sans-serif;
  background: #f6f7fb;
  color: #1f2937;
  padding: 32px;
}

.demo-box {
  max-width: 680px;
  margin: 0 auto;
  background: #ffffff;
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
}

h1 {
  color: #2563eb;
  margin-top: 0;
}

p {
  font-size: 18px;
  line-height: 1.6;
}

button {
  border: 0;
  background: #2563eb;
  color: #fff;
  padding: 12px 16px;
  border-radius: 10px;
  cursor: pointer;
}
CSS;

$defaultGlobalCss = <<<'GCSS'
https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css
GCSS;

$defaultGlobalJs = <<<'GJS'
https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js
GJS;

$defaultJs = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
  var button = document.getElementById('demoButton');
  if (button) {
    button.addEventListener('click', function () {
      console.log('Button geklickt');
    });
  }
});
JS;
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HTML/CSS/JS Editor in PHP</title>
  <style>
    * {
      box-sizing: border-box;
    }

    html,
    body {
      margin: 0;
      height: 100%;
      font-family: Arial, Helvetica, sans-serif;
      background: #0f172a;
      color: #e5e7eb;
    }

    body {
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    .topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      padding: 14px 18px;
      border-bottom: 1px solid #1e293b;
      background: #111827;
      flex-wrap: wrap;
    }

    .title {
      font-size: 18px;
      font-weight: 700;
    }

    .status {
      font-size: 13px;
      color: #94a3b8;
    }

    .actions {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }

    button {
      border: 1px solid #334155;
      background: #1e293b;
      color: #e5e7eb;
      padding: 10px 14px;
      border-radius: 10px;
      cursor: pointer;
      font-size: 14px;
      transition: background 0.2s ease, border-color 0.2s ease;
    }

    button:hover,
    button.active {
      background: #2563eb;
      border-color: #2563eb;
    }

    .main {
      flex: 1;
      min-height: 0;
      display: flex;
      overflow: hidden;
    }

    .editor-panel,
    .preview-panel {
      min-width: 0;
      min-height: 0;
      display: flex;
      flex-direction: column;
    }

    .editor-panel {
      width: 55%;
      background: #0b1220;
      border-right: 1px solid #1e293b;
    }

    .resizer {
      width: 10px;
      background: #0f172a;
      border-left: 1px solid #1e293b;
      border-right: 1px solid #1e293b;
      cursor: col-resize;
      position: relative;
      flex: 0 0 10px;
    }

    .resizer::after {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 4px;
      height: 46px;
      border-radius: 999px;
      background: #334155;
    }

    .preview-panel {
      flex: 1;
      background: #0b1220;
    }

    .tabs {
      display: flex;
      gap: 8px;
      padding: 12px;
      border-bottom: 1px solid #1e293b;
      background: #0f172a;
      flex-wrap: wrap;
    }

    .workspace {
      flex: 1;
      min-height: 0;
      position: relative;
      overflow: hidden;
    }

    .editor-wrap {
      position: absolute;
      inset: 0;
      display: none;
    }

    .editor-wrap.active {
      display: block;
    }

    textarea,
    pre,
    code {
      font-family: Consolas, Monaco, monospace;
      font-size: 14px;
      line-height: 1.6;
      tab-size: 2;
    }

    textarea {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      margin: 0;
      border: 0;
      resize: none;
      padding: 18px;
      color: transparent;
      caret-color: #f8fafc;
      background: transparent;
      overflow: auto;
      white-space: pre;
      outline: none;
      z-index: 2;
    }

    textarea::selection {
      background: rgba(59, 130, 246, 0.3);
    }

    pre {
      position: absolute;
      inset: 0;
      margin: 0;
      padding: 18px;
      overflow: auto;
      color: #e2e8f0;
      z-index: 1;
      pointer-events: none;
      white-space: pre-wrap;
      word-break: break-word;
    }

    .preview-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 12px;
      border-bottom: 1px solid #1e293b;
      background: #0f172a;
      flex-wrap: wrap;
    }

    .preview-actions {
      display: flex;
      gap: 8px;
      align-items: center;
      flex-wrap: wrap;
    }

    .preview-shell {
      flex: 1;
      min-height: 0;
      padding: 14px;
      overflow: auto;
      background: #020617;
    }

    .preview-stage {
      margin: 0 auto;
      width: 100%;
      min-width: 320px;
      max-width: 100%;
      height: 100%;
      min-height: 420px;
      background: #ffffff;
      border-radius: 14px;
      overflow: hidden;
      border: 1px solid #334155;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
      resize: both;
    }

    iframe {
      width: 100%;
      height: 100%;
      min-height: 420px;
      border: 0;
      background: #fff;
      display: block;
    }

    .token-tag { color: #93c5fd; }
    .token-name { color: #fca5a5; }
    .token-string { color: #86efac; }
    .token-comment { color: #64748b; }
    .token-keyword { color: #c4b5fd; }
    .token-number { color: #f9a8d4; }
    .token-css-key { color: #7dd3fc; }
    .token-css-value { color: #fde68a; }

    .hidden {
      display: none !important;
    }

    @media (max-width: 980px) {
      .main {
        flex-direction: column;
      }

      .editor-panel {
        width: 100% !important;
        height: 55%;
        border-right: 0;
        border-bottom: 1px solid #1e293b;
      }

      .resizer {
        display: none;
      }

      .preview-panel {
        flex: 1;
      }

      .preview-stage {
        resize: vertical;
        width: 100% !important;
      }
    }
  </style>
</head>
<body>
  <div class="topbar">
    <div>
      <div class="title">Editor in PHP + Vanilla JS</div>
      <div class="status">HTML, lokales CSS, globale CSS-Dateien, lokales JS und globale JS-Dateien</div>
    </div>
    <div class="actions">
      <button id="editModeBtn" class="active" type="button">Edit-Modus</button>
      <button id="previewModeBtn" type="button">Preview-Modus</button>
      <button id="runBtn" type="button">Neu rendern</button>
      <button id="resetBtn" type="button">Zurücksetzen</button>
      <button id="downloadBtn" type="button">Export HTML</button>
    </div>
  </div>

  <div class="main" id="mainLayout">
    <section class="editor-panel" id="editorPanel">
      <div class="tabs">
        <button class="tab-btn active" data-tab="html" type="button">HTML</button>
        <button class="tab-btn" data-tab="css" type="button">CSS</button>
        <button class="tab-btn" data-tab="global-css" type="button">Global CSS</button>
        <button class="tab-btn" data-tab="js" type="button">JS</button>
        <button class="tab-btn" data-tab="global-js" type="button">Global JS</button>
      </div>

      <div class="workspace">
        <div class="editor-wrap active" data-editor="html">
          <pre id="highlight-html"></pre>
          <textarea id="htmlEditor" spellcheck="false"><?php echo htmlspecialchars($defaultHtml, ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <div class="editor-wrap" data-editor="css">
          <pre id="highlight-css"></pre>
          <textarea id="cssEditor" spellcheck="false"><?php echo htmlspecialchars($defaultCss, ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <div class="editor-wrap" data-editor="global-css">
          <pre id="highlight-global-css"></pre>
          <textarea id="globalCssEditor" spellcheck="false"><?php echo htmlspecialchars($defaultGlobalCss, ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <div class="editor-wrap" data-editor="js">
          <pre id="highlight-js"></pre>
          <textarea id="jsEditor" spellcheck="false"><?php echo htmlspecialchars($defaultJs, ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <div class="editor-wrap" data-editor="global-js">
          <pre id="highlight-global-js"></pre>
          <textarea id="globalJsEditor" spellcheck="false"><?php echo htmlspecialchars($defaultGlobalJs, ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
      </div>
    </section>

    <div class="resizer" id="layoutResizer" title="Breite ziehen"></div>

    <section class="preview-panel" id="previewPanel">
      <div class="preview-header">
        <div>
          <strong>Live Preview</strong>
          <div class="status">Rechts direkt bearbeitbar. Änderungen werden zurück in HTML geschrieben.</div>
        </div>
        <div class="preview-actions">
          <span class="status">Globale CSS- und JS-Dateien zeilenweise eintragen</span>
        </div>
      </div>
      <div class="preview-shell">
        <div class="preview-stage" id="previewStage">
          <iframe id="previewFrame" title="Preview"></iframe>
        </div>
      </div>
    </section>
  </div>

  <script>
    const htmlEditor = document.getElementById('htmlEditor');
    const cssEditor = document.getElementById('cssEditor');
    const globalCssEditor = document.getElementById('globalCssEditor');
    const jsEditor = document.getElementById('jsEditor');
    const globalJsEditor = document.getElementById('globalJsEditor');

    const highlightHtml = document.getElementById('highlight-html');
    const highlightCss = document.getElementById('highlight-css');
    const highlightGlobalCss = document.getElementById('highlight-global-css');
    const highlightJs = document.getElementById('highlight-js');
    const highlightGlobalJs = document.getElementById('highlight-global-js');

    const previewFrame = document.getElementById('previewFrame');
    const previewStage = document.getElementById('previewStage');
    const runBtn = document.getElementById('runBtn');
    const resetBtn = document.getElementById('resetBtn');
    const downloadBtn = document.getElementById('downloadBtn');
    const editModeBtn = document.getElementById('editModeBtn');
    const previewModeBtn = document.getElementById('previewModeBtn');
    const previewPanel = document.getElementById('previewPanel');
    const editorPanel = document.getElementById('editorPanel');
    const layoutResizer = document.getElementById('layoutResizer');
    const mainLayout = document.getElementById('mainLayout');

    let syncPaused = false;
    let isDragging = false;

    const defaults = {
      html: htmlEditor.value,
      css: cssEditor.value,
      globalCss: globalCssEditor.value,
      js: jsEditor.value,
      globalJs: globalJsEditor.value
    };

    function escapeHtml(text) {
      return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    }

    function highlightHTMLSyntax(code) {
      let out = escapeHtml(code);
      out = out.replace(/(&lt;!--[\s\S]*?--&gt;)/g, '<span class="token-comment">$1</span>');
      out = out.replace(/(&lt;\/?)([a-zA-Z0-9\-]+)(.*?)(\/??&gt;)/g, function (_, open, tag, attrs, close) {
        const parsedAttrs = attrs.replace(/([a-zA-Z\-:]+)(=)(&quot;.*?&quot;|&#039;.*?&#039;)/g, '<span class="token-name">$1</span>$2<span class="token-string">$3</span>');
        return '<span class="token-tag">' + open + '</span><span class="token-name">' + tag + '</span>' + parsedAttrs + '<span class="token-tag">' + close + '</span>';
      });
      return out;
    }

    function highlightCSSSyntax(code) {
      let out = escapeHtml(code);
      out = out.replace(/(\/\*[\s\S]*?\*\/)/g, '<span class="token-comment">$1</span>');
      out = out.replace(/([\.#]?[a-zA-Z0-9_\-\s:,>\[\]"'=()*\/]+)(\s*\{)/g, '<span class="token-tag">$1</span>$2');
      out = out.replace(/([a-zA-Z\-]+)(\s*:)([^;\n]+)(;?)/g, '<span class="token-css-key">$1</span>$2<span class="token-css-value">$3</span>$4');
      return out;
    }

    function highlightJSSyntax(code) {
      let out = escapeHtml(code);
      out = out.replace(/(\/\/.*$)/gm, '<span class="token-comment">$1</span>');
      out = out.replace(/(\/\*[\s\S]*?\*\/)/g, '<span class="token-comment">$1</span>');
      out = out.replace(/(["'`])((?:\\.|(?!\1)[\s\S])*)(\1)/g, '<span class="token-string">$1$2$3</span>');
      out = out.replace(/\b(const|let|var|function|return|if|else|for|while|document|window|addEventListener|class|new|true|false|null|undefined)\b/g, '<span class="token-keyword">$1</span>');
      out = out.replace(/\b(\d+(?:\.\d+)?)\b/g, '<span class="token-number">$1</span>');
      return out;
    }

    function highlightUrlListSyntax(code) {
      let out = escapeHtml(code);
      out = out.replace(/^(https?:\/\/.*)$/gm, '<span class="token-string">$1</span>');
      return out;
    }

    function syncScroll(textarea, pre) {
      pre.scrollTop = textarea.scrollTop;
      pre.scrollLeft = textarea.scrollLeft;
    }

    function updateHighlights() {
      highlightHtml.innerHTML = highlightHTMLSyntax(htmlEditor.value);
      highlightCss.innerHTML = highlightCSSSyntax(cssEditor.value);
      highlightGlobalCss.innerHTML = highlightUrlListSyntax(globalCssEditor.value);
      highlightJs.innerHTML = highlightJSSyntax(jsEditor.value);
      highlightGlobalJs.innerHTML = highlightUrlListSyntax(globalJsEditor.value);
    }

    function escapeAttribute(value) {
      return value.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
    }

    function buildGlobalCssLinks() {
      return globalCssEditor.value
        .split(/\r?\n/)
        .map(function (line) { return line.trim(); })
        .filter(function (line) { return line !== ''; })
        .map(function (url) {
          return '<link rel="stylesheet" href="' + escapeAttribute(url) + '">';
        })
        .join('\n');
    }

    function buildGlobalJsScripts() {
      return globalJsEditor.value
        .split(/\r?\n/)
        .map(function (line) { return line.trim(); })
        .filter(function (line) { return line !== ''; })
        .map(function (url) {
          return '<script src="' + escapeAttribute(url) + '"><\\/script>';
        })
        .join('\n');
    }

    function buildPreviewDocument() {
      return `<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
${buildGlobalCssLinks()}
<style>
html, body { min-height: 100%; }
body[data-direct-edit='true'] { outline: none; }
body[data-direct-edit='true']:focus { outline: none; }
${cssEditor.value}
</style>
</head>
<body contenteditable="true" spellcheck="false" data-direct-edit="true">
${htmlEditor.value}
<script>
(function () {
  function sendUpdate() {
    if (!window.parent) return;
    window.parent.postMessage({
      type: 'preview-html-update',
      html: document.body.innerHTML
    }, '*');
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.body.addEventListener('input', sendUpdate);
    document.body.addEventListener('blur', sendUpdate, true);
    document.body.addEventListener('keyup', sendUpdate);
    document.body.addEventListener('paste', function () {
      setTimeout(sendUpdate, 0);
    });
  });
})();
<\/script>
${buildGlobalJsScripts()}
<script>
${jsEditor.value}
<\/script>
</body>
</html>`;
    }

    function renderPreview() {
      syncPaused = true;
      previewFrame.srcdoc = buildPreviewDocument();
      setTimeout(function () {
        syncPaused = false;
      }, 120);
    }

    function activateTab(tab) {
      document.querySelectorAll('.tab-btn').forEach(function (btn) {
        btn.classList.toggle('active', btn.dataset.tab === tab);
      });

      document.querySelectorAll('.editor-wrap').forEach(function (panel) {
        panel.classList.toggle('active', panel.dataset.editor === tab);
      });
    }

    function setMode(mode) {
      const previewOnly = mode === 'preview';
      editorPanel.classList.toggle('hidden', previewOnly);
      layoutResizer.classList.toggle('hidden', previewOnly);
      editModeBtn.classList.toggle('active', mode === 'edit');
      previewModeBtn.classList.toggle('active', mode === 'preview');
      previewPanel.classList.remove('hidden');
    }

    function buildExportDocument() {
      return `<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
${buildGlobalCssLinks()}
<style>${cssEditor.value}</style>
</head>
<body>
${htmlEditor.value}
${buildGlobalJsScripts()}
<script>
${jsEditor.value}
<\/script>
</body>
</html>`;
    }

    function exportHtml() {
      const blob = new Blob([buildExportDocument()], { type: 'text/html;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'export.html';
      a.click();
      URL.revokeObjectURL(url);
    }

    [
      [htmlEditor, highlightHtml, 'html'],
      [cssEditor, highlightCss, 'css'],
      [globalCssEditor, highlightGlobalCss, 'global-css'],
      [jsEditor, highlightJs, 'js'],
      [globalJsEditor, highlightGlobalJs, 'global-js']
    ].forEach(function (pair) {
      const editor = pair[0];
      const output = pair[1];

      editor.addEventListener('input', function () {
        updateHighlights();
        syncScroll(editor, output);
        renderPreview();
      });

      editor.addEventListener('scroll', function () {
        syncScroll(editor, output);
      });

      editor.addEventListener('keydown', function (event) {
        if (event.key === 'Tab') {
          event.preventDefault();
          const start = editor.selectionStart;
          const end = editor.selectionEnd;
          editor.value = editor.value.substring(0, start) + '  ' + editor.value.substring(end);
          editor.selectionStart = editor.selectionEnd = start + 2;
          updateHighlights();
          renderPreview();
        }
      });
    });

    document.querySelectorAll('.tab-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        activateTab(btn.dataset.tab);
      });
    });

    window.addEventListener('message', function (event) {
      if (!event.data || event.data.type !== 'preview-html-update' || syncPaused) {
        return;
      }

      const newHtml = event.data.html || '';
      if (newHtml !== htmlEditor.value) {
        const start = htmlEditor.selectionStart;
        const end = htmlEditor.selectionEnd;
        htmlEditor.value = newHtml;
        updateHighlights();
        htmlEditor.selectionStart = Math.min(start, htmlEditor.value.length);
        htmlEditor.selectionEnd = Math.min(end, htmlEditor.value.length);
      }
    });

    runBtn.addEventListener('click', renderPreview);

    resetBtn.addEventListener('click', function () {
      htmlEditor.value = defaults.html;
      cssEditor.value = defaults.css;
      globalCssEditor.value = defaults.globalCss;
      jsEditor.value = defaults.js;
      globalJsEditor.value = defaults.globalJs;
      updateHighlights();
      renderPreview();
    });

    downloadBtn.addEventListener('click', exportHtml);

    editModeBtn.addEventListener('click', function () {
      setMode('edit');
    });

    previewModeBtn.addEventListener('click', function () {
      setMode('preview');
    });

    layoutResizer.addEventListener('mousedown', function () {
      isDragging = true;
      document.body.style.cursor = 'col-resize';
      document.body.style.userSelect = 'none';
    });

    window.addEventListener('mousemove', function (event) {
      if (!isDragging || window.innerWidth <= 980) {
        return;
      }

      const bounds = mainLayout.getBoundingClientRect();
      const percent = ((event.clientX - bounds.left) / bounds.width) * 100;
      const clamped = Math.min(80, Math.max(20, percent));
      editorPanel.style.width = clamped + '%';
    });

    window.addEventListener('mouseup', function () {
      if (!isDragging) {
        return;
      }

      isDragging = false;
      document.body.style.cursor = '';
      document.body.style.userSelect = '';
    });

    previewStage.style.width = '100%';
    previewStage.style.height = '100%';

    updateHighlights();
    renderPreview();
    setMode('edit');
  </script>
</body>
</html>