# نسخة Serv00 — https://sari221.serv00.net

نسخة كاملة ومستقلة من الألعاب على حساب Serv00 المجاني (FreeBSD، PHP 8.3، بدون root).
قواعد بياناتها لحالها: الأسئلة المضافة من الأدمن والإحصائيات والغرف ما بتتشارك مع سيرفر أوراكل.

## التركيب (مرة وحدة)

```sh
ssh sari221@s16.serv00.com
fetch -o - https://raw.githubusercontent.com/sariazad/Faster/main/deploy/serv00/install.sh | sh
```

السكربت بيسحب المشروع لـ `~/domains/sari221.serv00.net/app`، وبينزّل المكتبات، وبيجهّز `.env` وقواعد البيانات،
وبيسألك عن إيميل وكلمة سر المشرف، وبيربط `public_html` بمجلد `public`.

## النشر التلقائي من GitHub

على السيرفر:

```sh
ssh-keygen -t ed25519 -f ~/.ssh/github_deploy -N "" -C github-deploy
cat ~/.ssh/github_deploy.pub >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys
cat ~/.ssh/github_deploy
```

انسخ كل اللي طلع (من `-----BEGIN` لـ `END-----`) وحطه بـ GitHub:
**Settings → Secrets and variables → Actions → New repository secret** باسم `SERV00_SSH_KEY`. بعدها احذف المفتاح الخاص من السيرفر:

```sh
rm ~/.ssh/github_deploy
```

من هون ورايح، كل تعديل بينجح باختباراته بيطلع على أوراكل وServ00 مع بعض.
الواجهة (Vite) بتنبني على GitHub وبتنرفع جاهزة، لأن ذاكرة الحساب 512MB بس.

## حدود الحساب المجاني

- 3 عمليات PHP بنفس الوقت: لعبة وحدة بالأجهزة المتعددة تمام، بس كذا غرفة مع بعض ممكن تبطّئ.
- 3GB مساحة و512MB ذاكرة.
