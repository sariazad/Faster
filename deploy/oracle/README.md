# رفع مين الأسبق على Oracle Cloud (Always Free)

بالآخر بيكون عندك سيرفر ARM مجاني، عليه HTTPS، والقاعدتين محفوظين، ونسخة احتياطية يومية. كل التثبيت بأمر واحد.

---

## 1) إنشاء الحساب
1. ادخل على https://www.oracle.com/cloud/free/ واضغط **Start for free**.
2. **Home Region:** اختار **Saudi Arabia Central (Riyadh)** أو **Saudi Arabia West (Jeddah)**. ⚠️ ما بتتغيّر بعد التسجيل.
3. بيطلب بطاقة للتحقق بس.
4. **مستحسن:** من **Billing → Upgrade and Manage Payment** حوّل الحساب لـ **Pay As You Go**، لحتى ما يسحبوا السيرفر إذا ضل واقف. الموارد المجانية بتضل مجانية.
5. **ضروري بعد الترقية:** من **Billing → Budgets** اعمل ميزانية بدولار واحد مع تنبيه على إيميلك، فإذا صار أي صرف بالغلط بتعرف فوراً.

## 2) إنشاء السيرفر
**Menu → Compute → Instances → Create instance**
- **Name:** `faster`
- **Image:** اضغط Change image ← **Ubuntu** ← **Canonical Ubuntu 24.04** (المكتوب جنبه aarch64).
- **Shape:** اضغط Change shape ← **Ampere** ← `VM.Standard.A1.Flex` ← **2 OCPU و 12 GB**. بتكفي كمان 1 OCPU و6 GB.
- **Networking:** خلّي الإعدادات الافتراضية، وتأكد إن **Assign a public IPv4 address** مفعّل.
- **Add SSH keys:** اختار **Generate a key pair for me** ونزّل **المفتاحين** واحفظهم منيح.
- اضغط **Create**. إذا طلعلك `Out of capacity`، جرّب Availability Domain تاني، أو أعد المحاولة بعد شوي.

لما يصير **Running**، انسخ الـ **Public IP address**.

## 3) فتح المنفذين 80 و 443 بلوحة Oracle
من صفحة السيرفر: **Primary VNIC → Subnet** ← **Security Lists** ← **Default Security List** ← **Add Ingress Rules**:

| Source CIDR | IP Protocol | Destination Port |
|---|---|---|
| `0.0.0.0/0` | TCP | `80` |
| `0.0.0.0/0` | TCP | `443` |

(جدار الحماية جوّا السيرفر نفسه، السكربت بيفتحه لحاله.)

## 4) الدومين (اختياري)
> **بدون دومين:** فيك تحط الـIP مكان الدومين (`sudo bash setup.sh 84.8.118.201`) واللعبة بتشتغل على `http://IP`. مفيد لما شبكة (متل الجامعة) تحجب DuckDNS أو sslip.io. ولما يصير عندك دومين، بتعيد تشغيل السكربت فيه وبتطلع شهادة HTTPS.

1. ادخل على https://www.duckdns.org وسجّل دخول بحساب Google أو GitHub.
2. اختار اسم، متل `meen-alasbaq`، فبيصير الدومين `meen-alasbaq.duckdns.org`.
3. بخانة **current ip** حط الـ Public IP تبع السيرفر واضغط **update ip**.

إذا عندك دومين خاص: اعمل سجل **A** بيأشر على الـ IP.

## 5) الدخول على السيرفر
**Mac / Linux:**
```bash
chmod 600 ~/Downloads/ssh-key-*.key
ssh -i ~/Downloads/ssh-key-*.key ubuntu@IP_السيرفر
```
**Windows (PowerShell):**
```powershell
ssh -i $HOME\Downloads\ssh-key-XXXX.key ubuntu@IP_السيرفر
```

## 6) التثبيت (أمر واحد)
```bash
curl -fsSL https://raw.githubusercontent.com/sariazad/Faster/main/deploy/oracle/setup.sh -o setup.sh
sudo bash setup.sh meen-alasbaq.duckdns.org your@email.com
```
بياخد حوالي 5 لـ10 دقايق. بالنص بيسألك عن **إيميل وكلمة سر المشرف** للوحة التحكم.

بعدها:
- اللعبة: `https://الدومين`
- ألف ياء: `https://الدومين/alefya`
- آخر القائمة: `https://الدومين/akher`
- لوحة التحكم: `https://الدومين/admin`

## 7) التحديث بعد كل تعديل على GitHub
```bash
sudo bash /var/www/faster/deploy/oracle/deploy.sh
```
السكربت بيعمل:
- نسخة احتياطية.
- بيسحب آخر نسخة من GitHub.
- بيبني الواجهة.
- بيشغّل الـ migrations.
- بيضيف الأسئلة الجديدة، والأسئلة الموجودة ما بيمسّها.

## 8) النسخ الاحتياطي
- كل يوم الساعة 3:30 الصبح بتنعمل نسخة من القاعدتين والوسائط بـ `/var/backups/faster/`، وبتنحفظ آخر 14 يوم.
- نسخة يدوية بأي وقت: `sudo faster-backup`
- لتنزّل نسخة على جهازك:
  ```bash
  scp -i KEY ubuntu@IP:/var/backups/faster/faster-*.tar.gz .
  ```
- وبتضل عندك نسخة بنك الأسئلة من لوحة التحكم (زر «نسخة احتياطية»).

## حل المشاكل
| المشكلة | الحل |
|---|---|
| الموقع ما بيفتح | تأكد من قواعد 80 و443 بالخطوة 3، وإن DuckDNS بيأشر على IP السيرفر الصح. |
| خطأ شهادة HTTPS | Caddy بيطلع الشهادة لحاله بس لما الدومين يأشر صح. شوف السجل بـ `sudo journalctl -u caddy -n 50` |
| خطأ 500 | `tail -n 50 /var/www/faster/storage/logs/laravel.log` |
| بدك تغيّر كلمة سر المشرف | `cd /var/www/faster && php artisan make:filament-user` |
