// 軽微な動作支援：
// - フッターの data-first-year / data-last-year を読み取り表示を整形
// - モバイル向けにサブメニューの開閉をクリックで切替（ホバーが効かない端末対応）
// - 重大なエラーは吐かない（保険）
document.addEventListener('DOMContentLoaded', function () {
  try {
    // COPYRIGHT 表示整形
    (function setCopyright(){
      const footerInner = document.querySelector('.footer-inner');
      const target = document.getElementById('siteCopyright');
      if (!footerInner || !target) return;
      const f = footerInner.getAttribute('data-first-year');
      const l = footerInner.getAttribute('data-last-year');
      const nowYear = new Date().getFullYear();
      let out = '';
      if (f && l) {
        if (f === l) out = `©${f} タイトル`;
        else out = `©${f}－${l} タイトル`;
      } else if (f && !l) {
        // first only -> range to current year
        if (f == String(nowYear)) out = `©${f} タイトル`;
        else out = `©${f}－${nowYear} タイトル`;
      } else {
        out = `©${nowYear} タイトル`;
      }
      target.textContent = out;
    })();

    // モバイル向け: .has-sub の a をクリックで toggle (aria-expanded)
    (function submenuToggle(){
      const hasSubs = document.querySelectorAll('.menu-item.has-sub > a');
      hasSubs.forEach(function (a){
        a.addEventListener('click', function (e) {
          // 画面幅が小さいときは toggle、幅が大きければ hover を使う（prevent default only on small）
          if (window.matchMedia('(max-width:900px)').matches) {
            e.preventDefault();
            const parent = a.parentElement;
            const expanded = a.getAttribute('aria-expanded') === 'true';
            a.setAttribute('aria-expanded', String(!expanded));
            const submenu = parent.querySelector('.submenu');
            if (submenu) submenu.style.display = expanded ? 'none' : 'block';
          }
        });
      });
    })();
  } catch (err) {
    // 保険：致命的なログは表示しないがコンソールには出す（開発時のみ有効化してください）
    // console.error(err);
  }
});
