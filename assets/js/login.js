// シンプルなクライアント側補助: バリデーションとパスワード表示切替、フォーム送信中のUI
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('loginForm');
  const email = document.getElementById('email');
  const password = document.getElementById('password');
  const emailError = document.getElementById('emailError');
  const passwordError = document.getElementById('passwordError');
  const formMessage = document.getElementById('formMessage');
  const loginBtn = document.getElementById('loginBtn');
  const pwToggle = document.querySelector('.pw-toggle');

  function validateEmailValue(v){
    return /\S+@\S+\.\S+/.test(v);
  }

  pwToggle && pwToggle.addEventListener('click', function(){
    const isShown = password.type === 'text';
    password.type = isShown ? 'password' : 'text';
    pwToggle.setAttribute('aria-pressed', String(!isShown));
    pwToggle.textContent = isShown ? '👁' : '🙈';
    password.focus();
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    let ok = true;
    emailError.textContent = '';
    passwordError.textContent = '';
    formMessage.hidden = true;
    formMessage.textContent = '';

    if (!email.value.trim()) {
      emailError.textContent = 'メールアドレスを入力してください';
      ok = false;
    } else if (!validateEmailValue(email.value.trim())) {
      emailError.textContent = '有効なメールアドレスを入力してください';
      ok = false;
    }

    if (!password.value) {
      passwordError.textContent = 'パスワードを入力してください';
      ok = false;
    }

    if (!ok) return;

    // UI: 送信中表示
    loginBtn.disabled = true;
    loginBtn.textContent = '送信中…';

    // 実際の送信は fetch で行う（例）。サーバ側で CSRF/TLS を必ず適用してください。
    fetch(form.action, {
      method: form.method || 'POST',
      credentials: 'include', // cookie を使う場合
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email: email.value.trim(),
        password: password.value
      })
    }).then(async res => {
      loginBtn.disabled = false;
      loginBtn.textContent = 'ログイン';
      if (res.ok) {
        // 成功：JSON に redirect が含まれる前提
        const js = await res.json().catch(()=>({}));
        const redirect = js.redirect || '/';
        window.location.href = redirect;
      } else {
        // 失敗：メッセージ表示
        const js = await res.json().catch(()=>({ message: 'ログインに失敗しました' }));
        formMessage.hidden = false;
        formMessage.textContent = js.message || 'ログインに失敗しました';
      }
    }).catch(err => {
      loginBtn.disabled = false;
      loginBtn.textContent = 'ログイン';
      formMessage.hidden = false;
      formMessage.textContent = '通信エラーが発生しました。時間を置いて再度お試しください。';
      console.error(err);
    });
  });
});
