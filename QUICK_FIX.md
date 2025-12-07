# Quick Fix for cPanel Subdirectory Setup

## Your Current Problem
- Accessing `gloveacademyapi.termresult.com/glove-academy` shows directory listing
- Have to use `gloveacademyapi.termresult.com/glove-academy/public/api/test` to access API
- Getting "Method Not Allowed" error when accessing root

## ✅ SOLUTION: Point Document Root to Public Folder

This is the **standard Laravel deployment** and will fix all your issues:

### Step 1: Update Document Root in cPanel

1. Log into cPanel
2. Go to **Subdomains** (or **Addon Domains** if that's how you set it up)
3. Find `gloveacademyapi.termresult.com`
4. Click **Change** or **Edit** next to the document root
5. Change it from:
   ```
   /home/username/public_html/glove-academy
   ```
   To:
   ```
   /home/username/public_html/glove-academy/public
   ```
   (Replace `username` with your actual cPanel username)

6. Click **Save** or **Change**

### Step 2: Remove Root .htaccess (Optional)

Since the document root is now pointing to `public/`, you can delete or rename the root `.htaccess` file:
```bash
cd /path/to/glove-academy-backend
mv .htaccess .htaccess.backup
```

The `public/.htaccess` will handle all routing.

### Step 3: Update .env File

Make sure your `.env` has:
```env
APP_URL=https://gloveacademyapi.termresult.com/glove-academy
```

### Step 4: Test

1. **Test API endpoint:**
   ```
   https://gloveacademyapi.termresult.com/glove-academy/api/test
   ```
   Should return: `{"status":"success","message":"API is working!"}`

2. **Test root:**
   ```
   https://gloveacademyapi.termresult.com/glove-academy/
   ```
   Should return API info JSON

---

## Why This Works

- **Standard Laravel Practice:** Document root → `public/` folder
- **No .htaccess complications:** The `public/.htaccess` handles everything
- **Clean URLs:** No need for `/public/` in the URL
- **No directory listing:** Public folder doesn't show directory contents

---

## Alternative: If You Must Keep Document Root at Project Root

If you can't change the document root, then:

1. Keep the root `.htaccess` file (the updated one)
2. Make sure `mod_rewrite` is enabled
3. Test: `gloveacademyapi.termresult.com/glove-academy/api/test`

But the **recommended solution above is much better** and is the standard way to deploy Laravel.

---

## Still Having Issues?

1. **Check file permissions:**
   ```bash
   chmod 644 public/.htaccess
   chmod 755 public
   ```

2. **Check mod_rewrite is enabled:**
   - In cPanel, go to **Apache Modules** or **Select PHP Version** → **Extensions**
   - Make sure `mod_rewrite` is enabled

3. **Clear Laravel caches:**
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan cache:clear
   ```

4. **Check error logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

---

**The document root change is the key fix!** Once you point it to `public/`, everything should work perfectly.

