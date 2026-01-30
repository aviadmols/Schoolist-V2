# תיעוד מנגנון ה-AI - Schoolist V2

## סקירה כללית
מנגנון ה-AI מאפשר יצירה מהירה של תוכן (הודעות, אנשי קשר, ילדים) באמצעות ניתוח טקסט או תמונה עם OpenRouter API.

---

## קבצים מרכזיים

### 1. Backend - Admin Panel (Filament)

#### `app/Filament/Resources/ClassroomResource/Pages/EditClassroom.php`
**תפקיד:** מנגנון ה-AI Quick Add באדמין
**פונקציות עיקריות:**
- `getHeaderActions()` - מגדיר את כפתור "AI Quick Add"
- `analyzeAiContent()` - מנתח תוכן באמצעות OpenRouter
- `confirmAiSuggestion()` - מאשר ויוצר תוכן מההצעה
- `retryAiSuggestion()` - מבטל ומאפשר ניסיון מחדש
- `createContentFromSuggestion()` - יוצר תוכן מההצעה (announcements, contacts, children)
- `showAiSuggestionNotification()` - מציג הודעה עם כפתורי אישור/ביטול
- `parseContentAnalyzerResponse()` - מפרסר את תגובת ה-AI
- `buildContentAnalyzerPrompt()` - בונה את ה-prompt לניתוח

**Livewire Events:**
- `confirm-ai-suggestion` - מאשר את ההצעה ויוצר תוכן
- `retry-ai-suggestion` - מבטל ומאפשר ניסיון מחדש

**Session Key:**
- `ai_quick_add_suggestion_{classroom_id}` - שומר את ההצעה ב-session

---

### 2. Backend - Frontend (Public)

#### `app/Http/Controllers/Classroom/FrontendAiAddController.php`
**תפקיד:** מנגנון ה-AI Quick Add בפרונטאנד (דף הכיתה)
**Routes:**
- `POST /class/{classroom}/ai-analyze` - מנתח תוכן
- `POST /class/{classroom}/ai-store` - שומר תוכן

**פונקציות עיקריות:**
- `analyze()` - מנתח תוכן באמצעות OpenRouter
- `store()` - שומר תוכן מההצעה
- `createAnnouncements()` - יוצר announcements
- `createContacts()` - יוצר אנשי קשר
- `createChildContactPage()` - יוצר דף קשר לילד
- `parseResponse()` - מפרסר את תגובת ה-AI
- `buildPrompt()` - בונה את ה-prompt לניתוח

---

### 3. Service Layer

#### `app/Services/Ai/OpenRouterService.php`
**תפקיד:** שירות לתקשורת עם OpenRouter API
**פונקציות עיקריות:**
- `requestContentAnalysis()` - שולח בקשה לניתוח תוכן
- `requestTimetableExtraction()` - שולח בקשה לחילוץ מערכת שעות
- `requestTemplateUpdate()` - שולח בקשה לעדכון תבנית
- `testConnection()` - בודק חיבור ל-OpenRouter
- `getLastError()` - מחזיר את השגיאה האחרונה

**Audit Logging:**
- כל בקשה מתועדת ב-audit log עם פרטים מלאים

---

### 4. Models

#### `app/Models/AiSetting.php`
**תפקיד:** מודל להגדרות AI
**Fields:**
- `classroom_id` - מזהה כיתה (nullable)
- `provider` - ספק AI (openrouter)
- `token` - טוקן API
- `model` - מודל ל-timetable extraction
- `timetable_prompt` - prompt ל-timetable
- `content_analyzer_model` - מודל לניתוח תוכן
- `content_analyzer_prompt` - prompt לניתוח תוכן
- `builder_template_prompt` - prompt לתבניות builder

#### `app/Models/Announcement.php`
**תפקיד:** מודל להודעות/אירועים/שיעורי בית
**Fields:**
- `classroom_id` - מזהה כיתה
- `user_id` - מזהה משתמש
- `type` - סוג (message, homework, event)
- `title` - כותרת
- `content` - תוכן
- `occurs_on_date` - תאריך
- `occurs_at_time` - שעה
- `location` - מיקום

**Boot Events:**
- `creating` - מעדכן את גודל המדיה של הכיתה

#### `app/Models/ImportantContact.php`
**תפקיד:** מודל לאנשי קשר חשובים
**Fields:**
- `classroom_id` - מזהה כיתה
- `first_name` - שם פרטי
- `last_name` - שם משפחה
- `role` - תפקיד
- `phone` - טלפון
- `email` - אימייל

#### `app/Models/Child.php` & `app/Models/ChildContact.php`
**תפקיד:** מודלים לילדים ואנשי קשר שלהם
**Fields:**
- `classroom_id` - מזהה כיתה
- `name` - שם
- `birth_date` - תאריך לידה
- `name` (ChildContact) - שם איש קשר
- `relation` - קשר (father, mother, other)
- `phone` - טלפון

---

### 5. Frontend - Template

#### `app/Services/Builder/TemplateManager.php`
**תפקיד:** מגדיר את ה-HTML/JS של popup-quick-add בפרונטאנד
**JavaScript Functions:**
- `setupClassroomPageFeatures()` - מגדיר את כל הפונקציונליות
- Event handlers ל-quick add popup
- Event handlers ל-AI confirm popup
- AJAX calls ל-`/class/{classroom}/ai-analyze` ו-`/class/{classroom}/ai-store`

**HTML Elements:**
- `#popup-quick-add` - פופאפ להוספה מהירה
- `#popup-ai-confirm` - פופאפ לאישור הצעה
- `#ai-quick-add-trigger` - כפתור פתיחה
- `#quick-add-text` - שדה טקסט
- `#quick-add-file` - שדה קובץ
- `#quick-add-submit` - כפתור שליחה
- `#ai-confirm-save` - כפתור אישור ושמירה

---

### 6. Routes

#### `routes/web.php`
**Routes:**
- `GET /class/{classroom}` - דף הכיתה (מכיל את ה-popup-quick-add)
- `POST /class/{classroom}/ai-analyze` - ניתוח תוכן
- `POST /class/{classroom}/ai-store` - שמירת תוכן

---

### 7. Admin Pages

#### `app/Filament/Pages/OpenRouterSettings.php`
**תפקיד:** דף הגדרות OpenRouter באדמין
**פונקציות:**
- הגדרת token, models, prompts
- בדיקת חיבור ל-OpenRouter

---

## זרימת עבודה

### באדמין (Filament):
1. משתמש לוחץ על כפתור "AI Quick Add"
2. מזין טקסט או בוחר תמונה
3. לוחץ "המשך"
4. `analyzeAiContent()` נקרא
5. OpenRouter מנתח את התוכן
6. מוצגת הודעה עם כפתורי "Confirm & Create" / "Retry"
7. לחיצה על "Confirm & Create" מפעילה `confirmAiSuggestion()`
8. `createContentFromSuggestion()` יוצר את התוכן
9. מוצגת הודעה על הצלחה/כשל

### בפרונטאנד:
1. משתמש לוחץ על כפתור "+" (FAB)
2. מזין טקסט או בוחר תמונה
3. לוחץ "המשך"
4. JavaScript שולח AJAX ל-`/class/{classroom}/ai-analyze`
5. OpenRouter מנתח את התוכן
6. מוצג popup עם פרטי ההצעה
7. לחיצה על "אשר ושמור" שולחת AJAX ל-`/class/{classroom}/ai-store`
8. התוכן נוצר
9. מוצגת הודעה על הצלחה/כשל

---

## תיקונים שבוצעו

1. **תיקון dispatch ב-notification action** - שינוי מ-`dispatch()` ל-`dispatchSelf()` כדי שהאירוע יגיע לאותו component
2. **הוספת validation checks** - בדיקות ל-`$this->record` ו-`auth()->id()` לפני יצירת תוכן
3. **שיפור error handling** - try-catch מקיף עם logging מפורט
4. **תיקון updateClassroomMediaSize** - טיפול בשגיאות שלא ימנע יצירת announcement
5. **שיפור טיפול בשגיאות ב-JavaScript** - מניעת כפילות בהודעות שגיאה

---

## בעיות ידועות

1. **שגיאות Alpine.js** - משתנים לא מוגדרים (`state`, `tab`, `isOpen`, `isShown`, `processingMessage`) - אלה מגיעים מ-Filament עצמו ולא מהקוד שלנו
2. **Livewire component errors** - שגיאות של "Could not find Livewire component in DOM tree" - יכולות להיות בגלל morphing issues ב-Livewire

---

## הערות חשובות

- כל בקשה ל-OpenRouter מתועדת ב-audit log
- ההצעה נשמרת ב-session למקרה של refresh
- יש logging מפורט לכל שלב של התהליך
- Request IDs משמשים למעקב אחר בקשות
