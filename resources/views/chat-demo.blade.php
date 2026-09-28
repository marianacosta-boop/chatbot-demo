<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Chat demo</title>
<style>
  body{font:15px/1.5 system-ui,sans-serif;background:#eef1f5;margin:0;color:#17202b}
  .wrap{max-width:720px;margin:32px auto;background:#fff;border:1px solid #d9dfe6;border-radius:12px;display:flex;flex-direction:column;height:80vh}
  .msgs{flex:1;overflow:auto;padding:16px;display:flex;flex-direction:column;gap:10px}
  .m{max-width:78%;padding:10px 14px;border-radius:14px;white-space:pre-wrap}
  .user{align-self:flex-end;background:#1b2a41;color:#fff}.bot{align-self:flex-start;background:#f1f4f7}
  .err{align-self:center;color:#b9530c;font-size:13px}
  .row{display:flex;gap:8px;padding:12px;border-top:1px solid #d9dfe6;align-items:center}
  input[type=text]{flex:1;padding:10px;border:1px solid #d9dfe6;border-radius:8px;font:inherit}
  button{padding:10px 16px;border:0;border-radius:8px;background:#0e7c7b;color:#fff;font:inherit;cursor:pointer}
  button:disabled{opacity:.5;cursor:default}
  #mic{background:#1b2a41;padding:10px 12px}
  #mic.on{background:#b9530c}
  label{display:flex;align-items:center;gap:4px;font-size:18px;cursor:pointer}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/marked/12.0.2/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.1.6/purify.min.js"></script>
<style>
  .bot table{border-collapse:collapse;margin:8px 0;font-size:14px}
  .bot th,.bot td{border:1px solid #d9dfe6;padding:6px 10px;text-align:left}
  .bot th{background:#eef1f5}
  .bot p{margin:0 0 8px} .bot ul,.bot ol{margin:4px 0 8px 20px}
</style>
</head>
<body>
<div class="wrap">
  <div class="msgs" id="msgs"></div>
  <div class="row">
    <input type="text" id="in" placeholder="Escreva como se fosse o cliente…" disabled>
    <button id="mic" title="Falar">🎤</button>
    <label title="Ler as respostas em voz alta"><input type="checkbox" id="speak"> 🔊</label>
    <button id="send" disabled>Enviar</button>
  </div>
</div>

<script>
const csrf  = document.querySelector('meta[name=csrf-token]').content;
const msgs  = document.getElementById('msgs');
const input = document.getElementById('in');
const btn   = document.getElementById('send');
const mic   = document.getElementById('mic');
const speakOn = document.getElementById('speak');
let conversationId = null;

// ---------- helpers ----------
const add  = (cls, text) => { const d = document.createElement('div'); d.className = 'm ' + cls; d.textContent = text; msgs.appendChild(d); msgs.scrollTop = msgs.scrollHeight; return d; };
const post = (url, body) => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: JSON.stringify(body || {}) }).then(r => r.json());
const show = (el, r) => {
  if (r && r.reply) {
    el.innerHTML = DOMPurify.sanitize(marked.parse(r.reply));
    speak(r.reply);
  } else { el.className = 'm err'; el.textContent = 'Erro: ' + ((r && r.message) || JSON.stringify(r)); }
};
const setBusy = b => { input.disabled = btn.disabled = b; if (!b) input.focus(); };

// ---------- text-to-speech (browser built-in) ----------
let lastReply = '';
let voices = [];
function loadVoices() { voices = window.speechSynthesis ? speechSynthesis.getVoices() : []; }
if (window.speechSynthesis) { loadVoices(); speechSynthesis.onvoiceschanged = loadVoices; }

function speak(text) {
  if (text) lastReply = text;
  if (!speakOn.checked || !window.speechSynthesis || !lastReply) return;
  speechSynthesis.cancel();
  const clean = lastReply.replace(/[*_#>|`]/g, ' ').replace(/\s+/g, ' ');
  const u = new SpeechSynthesisUtterance(clean);
  u.lang = 'pt-PT';
  const v = voices.find(v => v.lang === 'pt-PT') || voices.find(v => v.lang.startsWith('pt')) || voices[0];
  if (v) u.voice = v;
  u.onerror = e => console.warn('tts', e.error);
  speechSynthesis.speak(u);
}
speakOn.addEventListener('change', () => speakOn.checked ? speak() : speechSynthesis.cancel());
input.addEventListener('focus', () => window.speechSynthesis && speechSynthesis.cancel());

// ---------- speech-to-text (browser built-in; Chrome/Edge) ----------
const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
if (!SR) {
  mic.disabled = true; mic.title = 'Reconhecimento de voz não suportado neste browser';
} else {
  const rec = new SR();
  rec.lang = 'pt-PT';
  rec.interimResults = true;
  rec.continuous = false;
  let listening = false;

  mic.onclick = () => {
    if (window.speechSynthesis) speechSynthesis.cancel();
    listening ? rec.stop() : rec.start();
  };
  rec.onstart  = () => { listening = true; mic.classList.add('on'); mic.textContent = '⏹'; input.placeholder = 'A ouvir…'; };
  rec.onresult = e => { input.value = Array.from(e.results).map(r => r[0].transcript).join(''); };
  rec.onend    = () => {
    listening = false; mic.classList.remove('on'); mic.textContent = '🎤'; input.placeholder = 'Escreva como se fosse o cliente…';
    if (input.value.trim()) btn.onclick();   // remove this line to let the client review before sending
  };
  rec.onerror  = e => {
    listening = false; mic.classList.remove('on'); mic.textContent = '🎤';
    console.warn('speech', e.error);
    add('err', 'Voz: ' + e.error + (e.error === 'network' ? ' (o reconhecimento do Chrome precisa do serviço Google; experimente o Edge)' : ''));
  };
}

// ---------- conversation ----------
(async () => {
  const wait = add('bot', 'A abrir a conversa…');
  try {
    const r = await post('/chat/conversations');       // triggers the proactive greeting
    conversationId = r.conversation_id;
    show(wait, r);
  } catch (e) { wait.className = 'm err'; wait.textContent = 'Erro: ' + e; }
  setBusy(false);
})();

btn.onclick = async () => {
  const t = input.value.trim(); if (!t || btn.disabled) return;
  input.value = ''; add('user', t); setBusy(true);
  const wait = add('bot', 'A pensar…');
  try { const r = await post(`/chat/conversations/${conversationId}/messages`, { message: t }); show(wait, r); }
  catch (e) { wait.className = 'm err'; wait.textContent = 'Erro: ' + e; }
  setBusy(false);
};
input.addEventListener('keydown', e => { if (e.key === 'Enter') btn.onclick(); });
</script>
</body>
</html>