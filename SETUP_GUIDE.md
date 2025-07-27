# EVS Vloerverwarming Offerte - Setup Guide

## Quick Fix Summary

The following fixes have been implemented to resolve the frontend issues:

### ✅ **Fixed Issues:**

1. **Edit Quote Page Validation Error** - Fixed "Oppervlakte moet groter zijn dan 0." error
2. **WordPress Shortcode Critical Error** - Fixed fatal errors in form template
3. **Improved Error Handling** - Added graceful error handling throughout the system

### 🔧 **Code Changes Made:**

1. **Offer Model (`src/Models/Offer.php`)**:
   - Added `validate()` method that returns errors instead of throwing exceptions
   - Added support for both `area` and `area_m2` field names
   - Modified `calculatePrices()` to return success status and validation errors
   - Improved error handling with try-catch blocks

2. **Edit Offer Page (`edit-offer.php`)**:
   - Enhanced dependency checking and error handling
   - Added graceful handling of missing vendor dependencies
   - Improved form submission with proper validation error display
   - Added error messages in the UI for better user experience

3. **WordPress Form Template (`templates/forms/quote-form.php`)**:
   - Fixed fatal errors by safely accessing pricing constants with fallbacks
   - Added reflection-based constant access with error handling
   - Prevents critical errors when plugin classes are not available

## 🚀 **Required Setup Steps**

### Step 1: Install PHP and Composer

1. **Install PHP** (if not already installed):
   - Download PHP from https://www.php.net/downloads.php
   - Add PHP to your system PATH
   - Verify: `php --version`

2. **Install Composer**:
   - Download from https://getcomposer.org/download/
   - Install globally
   - Verify: `composer --version`

### Step 2: Install Dependencies

```bash
# Navigate to project directory
cd "c:\Users\joria\OneDrive\Documents\Software dingen\Bol tool\Latest-Git-Working (PC)\EVS"

# Install Composer dependencies
composer install
```

### Step 3: Configure Environment

1. **Copy the environment file**:
   ```bash
   copy .env.local .env
   ```

2. **Update `.env` with your actual values**:
   - Configure email settings if needed
   - Set admin password hash
   - WordPress database is used automatically

### Step 4: Verify WordPress Shortcode

1. **Check shortcode usage**: Ensure you're using `[evs_offerte_formulier]`
2. **Verify plugin activation**: Make sure the EVS plugin is active in WordPress
3. **Test the form**: The form should now load without critical errors

## 🔍 **Testing the Fixes**

### Test Edit Quote Page:
1. Navigate to an edit quote page
2. Try to save with valid data - should work without "Oppervlakte moet groter zijn dan 0." error
3. Try to save with invalid data - should show proper validation messages

### Test WordPress Shortcode:
1. Add `[evs_offerte_formulier]` to a WordPress page
2. The form should load without critical errors
3. Pricing should display with fallback values if needed

## 📋 **What Was Fixed**

### Issue 1: Edit Quote Page Validation
- **Problem**: Strict validation throwing exceptions
- **Solution**: Graceful validation with error arrays
- **Result**: Proper error messages instead of crashes

### Issue 2: WordPress Shortcode Critical Error
- **Problem**: Direct access to undefined constants/classes
- **Solution**: Safe constant access with fallbacks
- **Result**: Form loads even if plugin classes aren't fully available

### Issue 3: Missing Dependencies
- **Problem**: Missing vendor autoload and .env file
- **Solution**: Enhanced dependency checking and fallbacks
- **Result**: System works gracefully even with missing dependencies

## 🎯 **Next Steps**

1. **Install PHP and Composer** (if needed)
2. **Run `composer install`** to get dependencies
3. **Configure `.env`** with your actual credentials
4. **Test both the edit quote page and WordPress shortcode**

The system should now work correctly with proper error handling and validation!
