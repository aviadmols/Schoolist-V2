# תיעוד Commits והשינויים באתר – Schoolist V2

מסמך זה מסכם את כל ה-commits והשינויים שבוצעו בפרויקט במהלך הסשן.

---

## סיכום לפי נושאים

### 1. Builder – תבניות (HTML/JS בקבצים, עורך רק CSS)

**Commits:** `8b73849`, `745c150`

- **HTML ו-JS עברו לקבצים ב-Git:**
  - `resources/views/builder/templates/` – תבניות Blade (classroom/page, auth/login, auth/qlink, popup/*)
  - `resources/builder/scripts/` – JS (classroom/page.js, auth/qlink.js)
- **עורך תבניות** (`/admin/builder-templates/{id}/edit`):
  - רק בלוק CSS לעריכה; HTML ו-JS לא ניתנים לעריכה באדמין
  - שמירה אחת (ללא draft/publish) – מעדכן ישירות `published_css`
  - "Template info" ו-"Mock Data JSON" בסעיפים מקופלים
- **TemplateRenderer ו-TemplateManager:** טוענים HTML/JS מהקבצים (אם קיימים), ורק CSS מהמסד

---

### 2. דף כיתה – Refactor ל-Controller

**Commits:** `6581edc`

- **ClassroomShowController** – כל לוגיקת דף הכיתה (`/class/{classroom}`) הועברה מ-closure ב-`routes/web.php` ל-`ClassroomShowController::show()`
- **routes/web.php:**
  - מחיקת נתיב `/setup-admin` (מסוכן ל-Production)
  - `Route::get('/class/{classroom}', [ClassroomShowController::class, 'show'])`
- **תוצאה:** קוד קריא יותר ונוח לבדיקות

---

### 3. התחברות (Login) – רק טלפון + קוד אימות (OTP)

**Commits:** `6581edc`

- **login.blade.php** (תבנית Builder):
  - הוסר שדה הסיסמה
  - זרימה: הזנת טלפון → "שלח קוד אימות" → הזנת קוד → "אימות קוד"
  - שליחה ל-`POST /auth/otp/request` ו-`POST /auth/otp/verify` (JSON)
  - כותרת "בואו נתחבר", לינק "עדיין לא הצטרפתם? מוזמנים כאן" לרישום
  - טיפול בשגיאות ולידציה והפנייה לרישום אם המשתמש לא קיים

---

### 4. עיצוב Auth (מסכי התחברות/רישום)

**Commits:** `6581edc`

- **AuthLayout.vue:**
  - רקע מהקובץ:  
    `https://app.schoolist.co.il/storage/media/assets/sO5gHke0mVnuWc1HaWtJUIU55gYjr2nhX5bIjLp5.svg`
  - כרטיס לבן ממורכז עם צל ו-border-radius
  - פוטר: לוגו "schoolist", זכויות יוצרים והצהרת נגישות / תקנון / מדיניות פרטיות
- **app.css:** הוסרו סטיילים כפולים של `.page-auth`

---

### 5. AI Quick Add – תאריך היום בפרומט

**Commits:** `6581edc`, ובשינויים קודמים (c4bb515 וכו')

- **FrontendAiAddController:** קבלת `target_date` ו-`target_day_name` מה-frontend; הוספת בלוק `TARGET_DAY_FOR_CONTENT` לפרומט (ה-AI מקבל את תאריך היום הנבחר, למשל יום שלישי)
- **routes/web.php (דרך ClassroomShowController):** הוספת `selected_date` ו-`week_dates` ל-`$pageData`
- **classroom/page.js:** שליחת `target_date` ו-`target_day_name` לפי הטאב הפעיל (יום נבחר) ב-request ל-`/class/{id}/ai-analyze`

---

### 6. ביצועים ותיקוני באגים – דף כיתה ו-Builder

**Commits:** `6581edc`, `8b73849`, ועוד

- **builder/screen.blade.php:**
  - הסרת טעינת `@vite(['resources/js/app.js'])` מדפי Builder (כיתה וכו') – מקטין LCP ומונע שגיאות מ-app.js
  - הסתרת מסך הטעינה (loader) ב-`DOMContentLoaded` במקום `load`
- **classroom/page.js:**
  - תיקון שגיאת `dataset` (null check) ובדיקות הגנה
  - סגירת פופאף "אנשי קשר / ילדים" (popup-children) בלחיצה על אזור הפופאף, לא רק על X
- **TemplateManager:** ניקוי מתודות ישנות (getDefaultClassroomPageHtml, getDefaultQlinkPageHtml וכו'), שימוש ב-`getTemplateHtmlFromFile` ו-`week_dates` / `selected_date`

---

### 7. תיעוד ותיקונים נוספים (מההיסטוריה)

- **AI_MECHANISM_DOCUMENTATION.md** – תיעוד מנגנון ה-AI, פרומפטים ואבחון
- **AI Quick Add:** תיקוני שגיאות, לוגים, ולידציה, middleware, התאמת שמות שדות
- **ביצועים:** Eager loading ל-links ו-importantContacts, Cache TTL, מפתח cache בלי selectedDay
- **פופאפים:** תיקון פתיחת מודלים, הזזת קוד ל-setupClassroomPageFeatures, כפתור FAB לפתיחת Quick Add

---

## רשימת Commits (כרונולוגית, אחרונים ראשון)

| Commit    | תיאור קצר |
|----------|-------------|
| `6581edc` | Refactor: ClassroomShowController, login OTP, AuthLayout, builder fixes |
| `745c150` | Builder: עורך רק CSS, Template info ו-Mock Data מקופלים |
| `8b73849` | Builder: HTML/JS בקבצים, עורך CSS בלבד, שמירה אחת |
| `00885bb` | תיעוד מנגנון AI (AI_MECHANISM_DOCUMENTATION.md) |
| `c4bb515` | תאריך בפרומט, התאמת פופאפים (תצוגת תוכן וכו') |
| `36d8165` | שיפור הגדרות AI ו-announcement feed |
| `090ac3f` | תיקון preview user scope בתבניות |
| …        | תיקוני AI Quick Add, הודעות שגיאה, לוגים, ביצועים |

---

## קבצים מרכזיים ששונו

- **Routes:** `routes/web.php` – הסרת setup-admin, classroom → Controller
- **Auth:** `resources/views/builder/templates/auth/login.blade.php` – OTP בלבד
- **Layout Auth:** `resources/js/layouts/AuthLayout.vue` – רקע, כרטיס, פוטר
- **Builder:** `BuilderTemplateResource.php`, `EditBuilderTemplate.php`, `TemplateManager.php`, `TemplateRenderer.php`
- **כיתה:** `ClassroomShowController.php` (חדש), `resources/builder/scripts/classroom/page.js`
- **AI:** `FrontendAiAddController.php` – buildPrompt עם target_date/target_day_name
- **מסך Builder:** `resources/views/builder/screen.blade.php` – בלי Vite, loader ב-DOMContentLoaded
- **CSS:** `resources/css/app.css`

---

*נוצר על בסיס היסטוריית Git והשינויים שבוצעו בסשן.*
