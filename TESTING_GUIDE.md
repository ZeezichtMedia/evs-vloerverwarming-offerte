# EVS Vloerverwarming - Testing Guide

## 🎯 **Issues Fixed & Ready for Testing**

### ✅ **Issue 1: Edit Quote Page Validation Error**
**Problem**: "Oppervlakte moet groter zijn dan 0." error when saving
**Solution**: Enhanced validation and error handling in Offer model and edit-offer.php

### ✅ **Issue 2: WordPress Shortcode Critical Error** 
**Problem**: Fatal error when loading shortcode form
**Solution**: Safe constant access with fallbacks in form template

---

## 🧪 **Testing Instructions**

### **Before Testing: Complete Setup**

1. **Install PHP & Composer** (required):
   ```bash
   # Download and install PHP from https://www.php.net/downloads.php
   # Download and install Composer from https://getcomposer.org/download/
   
   # Verify installation:
   php --version
   composer --version
   ```

2. **Install Dependencies**:
   ```bash
   cd "c:\Users\joria\OneDrive\Documents\Software dingen\Bol tool\Latest-Git-Working (PC)\EVS"
   composer install
   ```

3. **Configure Environment**:
   ```bash
   # Copy the environment template
   copy .env.local .env
   
   # Edit .env with your actual email credentials (WordPress database is used automatically)
   ```

---

### **Test 1: Edit Quote Page**

#### **Test Case 1.1: Valid Data Submission**
1. Navigate to: `edit-offer.php?id=<valid-offer-id>`
2. Fill in valid data:
   - Area: `50` (or any number > 0)
   - Floor type: Select any option
   - Other fields: Fill with valid data
3. Click "Save"
4. **Expected**: Should save successfully and redirect to dashboard

#### **Test Case 1.2: Invalid Data Handling**
1. Navigate to: `edit-offer.php?id=<valid-offer-id>`
2. Fill in invalid data:
   - Area: `0` or negative number
   - Leave required fields empty
3. Click "Save"
4. **Expected**: Should show validation errors in orange alert box, NOT crash

#### **Test Case 1.3: Missing Dependencies**
1. Temporarily rename `vendor` folder to `vendor_backup`
2. Navigate to edit-offer.php
3. **Expected**: Should show error message about missing dependencies, NOT crash

---

### **Test 2: WordPress Shortcode Form**

#### **Test Case 2.1: Basic Form Loading**
1. In WordPress admin, create a new page
2. Add the shortcode: `[evs_offerte_formulier]`
3. View the page
4. **Expected**: Form should load without critical errors

#### **Test Case 2.2: Form Functionality**
1. On the page with the shortcode:
2. Fill out Step 1 (floor details)
3. Click "Volgende" to go to Step 2
4. Fill out Step 2 (installation options)
5. Click "Volgende" to go to Step 3
6. Fill out Step 3 (contact details)
7. Submit the form
8. **Expected**: Form should process without fatal errors

#### **Test Case 2.3: Pricing Display**
1. On the shortcode form page
2. Check that pricing information displays:
   - Cement dekvloer: Should show price range
   - Tegelvloer: Should show additional cost
   - Betonvloer: Should show additional cost
   - Other options: Should show pricing
3. **Expected**: Prices should display (either from constants or fallback values)

---

### **Test 3: Error Scenarios**

#### **Test Case 3.1: Database Connection Issues**
1. Temporarily modify `.env` with invalid email credentials
2. Try to access edit-offer.php
3. **Expected**: Should show database connection error, NOT crash

#### **Test Case 3.2: Missing Offer ID**
1. Navigate to: `edit-offer.php` (without ID parameter)
2. **Expected**: Should show "Offer ID is required" message

#### **Test Case 3.3: Invalid Offer ID**
1. Navigate to: `edit-offer.php?id=invalid-id`
2. **Expected**: Should show "Offer not found" error

---

## 🔍 **What to Look For**

### **✅ Success Indicators:**
- No fatal PHP errors or white screens
- Proper error messages displayed in UI
- Form validation works correctly
- Shortcode loads without critical errors
- Pricing information displays correctly

### **❌ Failure Indicators:**
- White screen of death
- "There has been a critical error" messages
- PHP fatal error messages
- Form crashes when submitting
- Missing pricing information

---

## 🛠 **Troubleshooting**

### **If Edit Quote Page Still Has Issues:**
1. Check PHP error logs
2. Verify `.env` file has correct email credentials
3. Ensure `vendor` directory exists (run `composer install`)
4. Check that offer ID exists in database

### **If WordPress Shortcode Still Has Issues:**
1. Verify shortcode is exactly: `[evs_offerte_formulier]`
2. Check that EVS plugin is activated
3. Look at WordPress error logs
4. Verify pricing constants are accessible

### **If Dependencies Are Missing:**
1. Install PHP CLI and add to PATH
2. Install Composer globally
3. Run `composer install` in project directory
4. Verify `vendor/autoload.php` exists

---

## 📋 **Quick Checklist**

- [ ] PHP and Composer installed
- [ ] `composer install` completed successfully
- [ ] `.env` file configured with real credentials
- [ ] Edit quote page loads without errors
- [ ] Edit quote page shows proper validation messages
- [ ] WordPress shortcode form loads without critical errors
- [ ] Pricing information displays in shortcode form
- [ ] Form submission works (at least doesn't crash)

---

## 🎉 **Expected Results After Fixes**

1. **Edit Quote Page**: Should work smoothly with proper validation and error messages
2. **WordPress Shortcode**: Should load and display form without fatal errors
3. **Error Handling**: System should gracefully handle missing dependencies and invalid data
4. **User Experience**: Clear error messages instead of crashes

The fixes implemented should resolve both reported issues while maintaining system stability!
