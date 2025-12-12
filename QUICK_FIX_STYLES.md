# Quick Fix for Landing Page Styles

## The Problem
The landing page styles are broken because:
1. APP_URL was set to `http://localhost` instead of the actual domain
2. Frontend assets need to be rebuilt after configuration changes
3. Manifest file location might be incorrect

## Quick Fix (Run with sudo):

```bash
cd /home/deploy_user_dagi/services/table_track/restaurant
sudo ./fix-styles.sh
```

## Manual Fix Steps:

1. **Update APP_URL:**
   ```bash
   sed -i 's|APP_URL=.*|APP_URL=https://restaurant.akmicroservice.com|' .env
   ```

2. **Rebuild assets:**
   ```bash
   npm run build
   ```

3. **Ensure manifest is in correct location:**
   ```bash
   cp public/build/.vite/manifest.json public/build/manifest.json
   ```

4. **Clear caches:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan view:clear
   ```

5. **Restart service:**
   ```bash
   sudo systemctl restart restaurant.service
   ```

6. **Clear browser cache** (Ctrl+Shift+R or Cmd+Shift+R)

## Verify It's Working:

1. Check assets are loading: `curl -I http://restaurant.akmicroservice.com/build/assets/app-*.css`
2. Check page source for correct asset URLs
3. Open browser console and check for 404 errors on CSS/JS files

