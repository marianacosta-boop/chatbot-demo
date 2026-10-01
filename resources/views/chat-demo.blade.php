<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>Chat demo</title>

<script src="https://cdnjs.cloudflare.com/ajax/libs/marked/12.0.2/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.1.6/purify.min.js"></script>

<style>
  * {
    box-sizing: border-box;
  }

  body {
    font: 15px/1.5 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    background: #eef1f5;
    margin: 0;
    color: #17202b;
  }

  /* =========================================================
     FLOATING CHAT WINDOW
     ========================================================= */

  .wrap {
    position: fixed;

    right: 24px;
    bottom: 24px;

    width: 380px;
    height: 560px;

    background: #fff;

    border: 1px solid #d9dfe6;
    border-radius: 16px;

    display: flex;
    flex-direction: column;

    overflow: hidden;

    box-shadow: 0 10px 35px rgba(0, 0, 0, 0.15);

    z-index: 9999;
  }

  /* =========================================================
     CHAT HEADER
     ========================================================= */

  .chat-header {
    background: #6B1818;
    color: #fff;

    padding: 13px 15px;

    min-height: 54px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    flex-shrink: 0;
  }

  .chat-title {
    display: flex;
    flex-direction: column;
  }

  .chat-title strong {
    font-size: 15px;
    font-weight: 600;
    line-height: 1.3;
  }

  .chat-title span {
    font-size: 12px;
    opacity: 0.85;
    margin-top: 2px;
  }

  #close-chat {
    background: transparent;

    color: #fff;

    border: 0;

    font-size: 23px;

    padding: 3px 7px;

    cursor: pointer;

    line-height: 1;

    border-radius: 6px;
  }

  #close-chat:hover {
    background: rgba(255, 255, 255, 0.15);
  }

  /* =========================================================
     MESSAGES
     ========================================================= */

  .msgs {
    flex: 1;

    overflow-y: auto;

    padding: 16px;

    display: flex;
    flex-direction: column;

    gap: 10px;

    background: #fff;

    scroll-behavior: smooth;
  }

  /* Scrollbar */

  .msgs::-webkit-scrollbar {
    width: 6px;
  }

  .msgs::-webkit-scrollbar-track {
    background: transparent;
  }

  .msgs::-webkit-scrollbar-thumb {
    background: #cbd2da;
    border-radius: 10px;
  }

  .msgs::-webkit-scrollbar-thumb:hover {
    background: #aeb7c2;
  }

  /* =========================================================
     MESSAGE BUBBLES
     ========================================================= */

  .m {
    max-width: 82%;

    padding: 10px 13px;

    border-radius: 14px;

    font-size: 12px;

    line-height: 1.55;

    word-wrap: break-word;

    overflow-wrap: break-word;
  }

  .user {
    align-self: flex-end;

    background: #f2f2f2;

    color: #141414;

    white-space: pre-wrap;

    border-bottom-right-radius: 5px;
  }

  .bot {
    align-self: flex-start;

    background: #f1f4f7;

    color: #17202b;

    line-height: 1.55;

    border-bottom-right-radius: 5px;
  }

  /* =========================================================
     BOT TEXT SPACING
     ========================================================= */

  .bot p {
    margin: 0 0 7px 0;
  }

  .bot p:last-child {
    margin-bottom: 0;
  }

  .bot br {
    line-height: 1.8;
  }

  .bot strong {
    font-weight: 650;
  }

  .bot ul,
  .bot ol {
    margin: 7px 0 8px 20px;
    padding: 0;
  }

  .bot li {
    margin-bottom: 4px;
  }

  .bot li:last-child {
    margin-bottom: 0;
  }

  .bot h1,
  .bot h2,
  .bot h3,
  .bot h4 {
    margin: 8px 0 6px 0;
    line-height: 1.35;
  }

  .bot h1 {
    font-size: 18px;
  }

  .bot h2 {
    font-size: 16px;
  }

  .bot h3,
  .bot h4 {
    font-size: 15px;
  }

  /* =========================================================
     MARKDOWN TABLES
     ========================================================= */

  .bot table {
    border-collapse: collapse;

    width: 100%;

    margin: 9px 0;

    font-size: 13px;

    background: #fff;

    overflow: hidden;
  }

  .bot th,
  .bot td {
    border: 1px solid #d9dfe6;

    padding: 7px 9px;

    text-align: left;

    line-height: 1.4;

    vertical-align: top;
  }

  .bot th {
    background: #eef1f5;

    font-weight: 600;
  }

  /* =========================================================
     CODE
     ========================================================= */

  .bot code {
    background: #e5e9ed;

    padding: 2px 4px;

    border-radius: 4px;

    font-size: 12px;
  }

  .bot pre {
    background: #17202b;

    color: #fff;

    padding: 10px;

    border-radius: 7px;

    overflow-x: auto;

    font-size: 12px;
  }

  /* =========================================================
     ERROR
     ========================================================= */

  .err {
    align-self: center;

    color: #b9530c;

    font-size: 13px;

    padding: 5px;
  }

  /* =========================================================
     INPUT BAR
     ========================================================= */

  /* =========================================================
   INPUT BAR
   ========================================================= */

.row {
  display: flex;

  gap: 6px;

  padding: 10px 12px;

  border-top: 1px solid #d9dfe6;

  align-items: flex-end;

  background: #fff;

  flex-shrink: 0;
}


/* Text box */

.row textarea {
  flex: 1;

  min-width: 0;

  min-height: 42px;

  max-height: 104px;

  padding: 9px 13px;

  border: 1px solid #d9dfe6;

  border-radius: 10px;

  font: inherit;

  font-size: 14px;

  outline: none;

  color: #17202b;

  background: #fff;

  line-height: 1.45;

  resize: none;

  overflow-y: auto;
}


.row textarea:focus {
  border-color: #6B1818;

  box-shadow:
    0 0 0 2px rgba(14, 124, 123, 0.08);
}


.row textarea:disabled {
  background: #f5f6f7;
}


/* =========================================================
   SMALL ACTION BUTTONS
   ========================================================= */

.row button {
  width: 36px;

  height: 36px;

  padding: 0;

  border: 0;

  border-radius: 50%;

  display: flex;

  align-items: center;

  justify-content: center;

  flex-shrink: 0;

  cursor: pointer;

  transition:
    background 0.15s ease,
    opacity 0.15s ease,
    transform 0.1s ease;
}


/* Microphone */

#mic {
  background: transparent;

  color: #1b2a41;

  font-size: 16px;
}


#mic:hover:not(:disabled) {
  background: #eef1f5;
}


#mic.on {
  background: #fff0e8;

  color: #b9530c;
}


/* Sound */

#speak {
  background: transparent;

  color: #1b2a41;

  font-size: 15px;
}


#speak:hover {
  background: #eef1f5;
}


/* Active sound */

#speak.active {
  background: #e5f4f3;

  color: #0e7c7b;
}


/* Send */

#send {
  background: #6B1818;

  color: #fff;

  font-size: 22px;

  font-weight: 400;

  line-height: 1;

  padding-bottom: 3px;
}


#send:hover:not(:disabled) {
  background: #6b18188e;
  transform: scale(1.04);
}


#send:disabled {
  opacity: 0.45;

  cursor: default;
}


  /* =========================================================
     OPEN CHAT BUTTON
     ========================================================= */

  #open-chat {
    position: fixed;

    right: 24px;
    bottom: 24px;

    width: 58px;
    height: 58px;

    border-radius: 50%;

    background: #6B1818;

    color: #fff;

    border: none;

    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.20);

    font-size: 24px;

    cursor: pointer;

    z-index: 10000;

    display: none;

    align-items: center;
    justify-content: center;

    padding: 0;
  }

  #open-chat:hover {
    background: #6b18188e;
  }

  /* =========================================================
     MOBILE
     ========================================================= */

  @media (max-width: 600px) {

    .wrap {
      left: 10px;
      right: 10px;
      bottom: 10px;

      width: auto;

      height: calc(100vh - 20px);

      max-height: 700px;

      border-radius: 14px;
    }

    #open-chat {
      right: 16px;
      bottom: 16px;
    }

    .m {
      max-width: 88%;
    }

    .row {
      padding: 8px;
    }

    .row button {
      padding: 10px;
    }
  }
</style>
</head>

<body>

<!-- =========================================================
     CHAT WINDOW
     ========================================================= -->

<div class="wrap" id="chat-window">

  <!-- Header -->

  <div class="chat-header">

    <div class="chat-title">
      <strong>Assistente virtual AcinGOV</strong>
      <span>Em que posso ajudar?</span>
    </div>

    <button
      id="close-chat"
      title="Fechar"
      aria-label="Fechar chat"
    >×</button>

  </div>

  <!-- Messages -->

  <div class="msgs" id="msgs"></div>

  <!-- Input -->

  <div class="row">

  <textarea
    id="in"
    rows="1"
    maxlength="4000"
    placeholder="Escreva a sua questão..."
    disabled
    autocomplete="off"
  ></textarea>

  <button
    id="mic"
    title="Falar"
    aria-label="Falar"
  >
    🎤
  </button>

  <button
    id="speak"
    title="Ouvir resposta"
    aria-label="Ouvir resposta"
    type="button"
  >
    🔊
  </button>

  <button
    id="send"
    title="Enviar"
    aria-label="Enviar"
    disabled
  >
    →
  </button>

</div>


</div>

<!-- =========================================================
     OPEN CHAT BUTTON
     ========================================================= -->

<button
  id="open-chat"
  title="Abrir chat"
  aria-label="Abrir chat"
>💬</button>


<script>

/* =========================================================
   BASIC ELEMENTS
   ========================================================= */

const csrf = document
  .querySelector('meta[name=csrf-token]')
  .content;

const msgs = document.getElementById('msgs');

const input = document.getElementById('in');

const btn = document.getElementById('send');

const resizeInput = () => {

  input.style.height = 'auto';

  input.style.height =
    `${Math.min(input.scrollHeight, 104)}px`;

};

input.addEventListener('input', resizeInput);

const mic = document.getElementById('mic');

const speakOn = document.getElementById('speak');

let speakEnabled = false;

speakOn.addEventListener('click', () => {

  speakEnabled = !speakEnabled;

  speakOn.classList.toggle(
    'active',
    speakEnabled
  );

  if (speakEnabled) {
    speak();
  } else if (window.speechSynthesis) {
    speechSynthesis.cancel();
  }

});


const chatWindow = document.getElementById('chat-window');

const closeChat = document.getElementById('close-chat');

const openChat = document.getElementById('open-chat');

const chatBase = @json(auth()->check() ? '/chat' : '/chat/guest');

let conversationId = null;


/* =========================================================
   MESSAGE HELPER
   ========================================================= */

const add = (cls, text) => {

  const d = document.createElement('div');

  d.className = 'm ' + cls;

  d.textContent = text;

  msgs.appendChild(d);

  msgs.scrollTop = msgs.scrollHeight;

  return d;

};


/* =========================================================
   STREAMING POST / SSE
   ========================================================= */

async function streamPost(url, body, el) {

  const res = await fetch(url, {

    method: 'POST',

    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrf,
      'Accept': 'text/event-stream'
    },

    body: JSON.stringify(body || {}),

  });


  if (!res.ok || !res.body) {

    throw new Error(
      `${res.status} ${res.statusText}`
    );

  }


  const reader = res.body.getReader();

  const decoder = new TextDecoder();

  let buf = '';

  let full = '';

  let conversationId = null;


  while (true) {

    const {
      value,
      done
    } = await reader.read();


    if (done) break;


    buf += decoder.decode(
      value,
      { stream: true }
    );


    let sep;


    while (
      (sep = buf.indexOf('\n\n')) !== -1
    ) {

      const rawEvent =
        buf.slice(0, sep);

      buf =
        buf.slice(sep + 2);


      const line =
        rawEvent
          .split('\n')
          .find(
            l => l.startsWith('data:')
          );


      if (!line) continue;


      const data =
        JSON.parse(
          line.slice(5).trim()
        );


      if (data.error) {

        throw new Error(data.error);

      }


      if (data.conversation_id) {

        conversationId =
          data.conversation_id;

      }


      if (data.delta) {

        full += data.delta;

      }


      if (typeof data.reply === 'string') {

        full = data.reply;

      }

    }

  }


  /*
   * Render Markdown safely.
   */

  el.innerHTML =
    DOMPurify.sanitize(
      marked.parse(full)
    );


  msgs.scrollTop =
    msgs.scrollHeight;


  return {
    reply: full,
    conversation_id: conversationId
  };

}


/* =========================================================
   BUSY STATE
   ========================================================= */

const setBusy = b => {

  input.disabled = b;

  btn.disabled = b;

  if (!b) {

    input.focus();

  }

};


/* =========================================================
   TEXT TO SPEECH
   ========================================================= */

let lastReply = '';

let voices = [];


function loadVoices() {

  voices =
    window.speechSynthesis
      ? speechSynthesis.getVoices()
      : [];

}


if (window.speechSynthesis) {

  loadVoices();

  speechSynthesis.onvoiceschanged =
    loadVoices;

}


function speak(text) {

  if (text) {

    lastReply = text;

  }


  if (
  !speakEnabled ||
  !window.speechSynthesis ||
  !lastReply
) {
  return;
}



  speechSynthesis.cancel();


  /*
   * Remove basic Markdown formatting
   * before sending text to speech.
   */

  const clean =
    lastReply
      .replace(/[*_#>|`]/g, ' ')
      .replace(/\s+/g, ' ');


  const u =
    new SpeechSynthesisUtterance(clean);


  u.lang = 'pt-PT';


  const v =
    voices.find(
      v => v.lang === 'pt-PT'
    )
    ||
    voices.find(
      v => v.lang.startsWith('pt')
    )
    ||
    voices[0];


  if (v) {

    u.voice = v;

  }


  u.onerror = e => {

    console.warn(
      'tts',
      e.error
    );

  };


  speechSynthesis.speak(u);

}


speakOn.addEventListener(
  'change',
  () => {

    if (speakOn.checked) {

      speak();

    } else {

      speechSynthesis.cancel();

    }

  }
);


input.addEventListener(
  'focus',
  () => {

    if (window.speechSynthesis) {

      speechSynthesis.cancel();

    }

  }
);


/* =========================================================
   SPEECH TO TEXT
   ========================================================= */

const SR =
  window.SpeechRecognition ||
  window.webkitSpeechRecognition;


if (!SR) {

  mic.disabled = true;

  mic.title =
    'Reconhecimento de voz não suportado neste browser';

} else {

  const rec = new SR();


  rec.lang = 'pt-PT';

  rec.interimResults = true;

  rec.continuous = false;


  let listening = false;


  mic.onclick = () => {

    if (window.speechSynthesis) {

      speechSynthesis.cancel();

    }


    if (listening) {

      rec.stop();

    } else {

      rec.start();

    }

  };


  rec.onstart = () => {

    listening = true;

    mic.classList.add('on');

    mic.textContent = '⏹';

    mic.setAttribute(
      'aria-label',
      'Parar gravação'
    );

    input.placeholder = 'A ouvir…';

  };


  rec.onresult = e => {

    input.value =
      Array.from(e.results)
        .map(
          r => r[0].transcript
        )
        .join('');

      resizeInput();

  };


  rec.onend = () => {

    listening = false;

    mic.classList.remove('on');

    mic.textContent = '🎤';

    mic.setAttribute(
      'aria-label',
      'Falar'
    );

    input.placeholder =
      'Escreva como se fosse o cliente…';


    /*
     * Automatically send recognized speech.
     *
     * Remove this line if you want the
     * client to review the text first.
     */

    if (input.value.trim()) {

      btn.onclick();

    }

  };


  rec.onerror = e => {

    listening = false;

    mic.classList.remove('on');

    mic.textContent = '🎤';


    console.warn(
      'speech',
      e.error
    );


    add(
      'err',
      'Voz: ' +
      e.error +
      (
        e.error === 'network'
          ? ' (o reconhecimento do Chrome precisa do serviço Google; experimente o Edge)'
          : ''
      )
    );

  };

}


/* =========================================================
   OPEN / CLOSE CHAT
   ========================================================= */

closeChat.addEventListener(
  'click',
  () => {

    chatWindow.style.display = 'none';

    openChat.style.display = 'flex';

  }
);


openChat.addEventListener(
  'click',
  () => {

    chatWindow.style.display = 'flex';

    openChat.style.display = 'flex';

    openChat.style.display = 'none';

    input.focus();

  }
);


/* =========================================================
   START CONVERSATION
   ========================================================= */

(async () => {

  const wait =
    add(
      'bot',
      'A abrir a conversa…'
    );


  try {

    const r =
      await streamPost(
        `${chatBase}/conversations`,
        null,
        wait
      );


    conversationId =
      r.conversation_id;


    speak(r.reply);

  } catch (e) {

    wait.className =
      'm err';

    wait.textContent =
      'Erro: ' + e.message;

  }


  setBusy(false);

})();


/* =========================================================
   SEND MESSAGE
   ========================================================= */

btn.onclick = async () => {

  const t =
    input.value.trim();


  if (!t || btn.disabled) {

    return;

  }


  input.value = '';

  resizeInput();


  add(
    'user',
    t
  );


  setBusy(true);


  const wait =
    add(
      'bot',
      'A pensar…'
    );


  try {

    const r =
      await streamPost(
        `${chatBase}/conversations/${conversationId}/messages`,
        {
          message: t
        },
        wait
      );


    speak(r.reply);

  } catch (e) {

    wait.className =
      'm err';

    wait.textContent =
      'Erro: ' + e.message;

  }


  setBusy(false);

};


/* =========================================================
   ENTER TO SEND
   ========================================================= */

input.addEventListener(
  'keydown',
  e => {

    if (
      e.key === 'Enter' &&
      !e.shiftKey
    ) {

      e.preventDefault();

      btn.onclick();

    }

  }
);

</script>

</body>
</html>
