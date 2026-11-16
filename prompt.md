# MASTER PROMPT: SPG PAYROLL & INCENTIVE CALCULATION ENGINE (LARAVEL)

## 1. PROJECT OVERVIEW & TECH STACK
Build a complete, production-ready, desktop-first web application using **Laravel 11/12 (PHP 8.2+)**, **Blade + Alpine.js** (or Livewire 3 for reactive grid editing), **Tailwind CSS**, **MySQL/SQLite**, and **Maatwebsite/Laravel-Excel** (or PhpSpreadsheet) for reading and exporting complex 2D matrix Excel files.

The application is a specialized **Payroll (Salary) & Incentive (Commission) Calculation Engine** for SPG (Sales Promotion Girls) across multiple retail outlets. It is designed to ingest legacy, non-standardized Excel templates (1 file with 2 sheets: Presensi & Omzet), detect and fix human-entry typos/anomalies, transform the data into an interactive horizontal Pivot Matrix on the web, calculate Salary and Incentive using strict business logic, manage work-in-progress via a Task/Project Hub, and provide multi-period/annual executive financial dashboards with horizontal matrix Excel exports.

---

## 2. AUTHENTICATION & CUTE INTERACTIVE ORANGE CAT LOGIN PAGE
- **Single-Owner Authentication:** Secure login system for a single administrator (Email/Username & Password, session protection across all routes, password change menu, and seed default admin account).
- **Playful Animated Login UI ("Kucing Oren" Theme):**
  - Design a charming, high-craft, playful login page optimized for desktop/laptop screens featuring an interactive **Cute Orange Tabby Cat ("Kucing Oren") SVG/CSS animation**.
  - **Interactive States:**
    1. *Typing Username/Email:* The orange cat's eyes and head playfully follow the cursor/input progress.
    2. *Typing Password:* The orange cat covers its eyes with its paws (peeking slightly if "Show Password" is toggled).
    3. *Clicking the "Login" Button:* Trigger a funny, energetic animation—the orange cat pounces/jumps excitedly onto the login button, wags its tail, gives a cute "meow/purr" visual badge, and dashes across the screen as the page transitions into the Homepage Dashboard.
    4. *Failed Login:* The orange cat tilts its head confusedly with a sweat drop or hisses playfully when credentials are wrong.

---

## 3. UI/UX & DESKTOP-FIRST LAYOUT ARCHITECTURE
- **Laptop/Desktop Wide-Screen Optimization:** Designed specifically for wide laptop/desktop browsers (`max-w-full`).
- **Freeze Panes / Sticky Matrix Headers:** All horizontal date-matrix tables (30–31 date columns + summary calculation columns) MUST have **sticky left columns** (No, SPG Name, Assigned Outlet) and **sticky top date headers** so scrolling horizontally feels exactly like desktop Excel.
- **Navigation:** Top or collapsible sidebar navigation featuring:
  1. **Task Hub (Homepage)**
  2. **Executive & Annual Dashboard**
  3. **Master Data & Constants (Outlets, SPGs, Alias Dictionary, Incentive Tiers)**
  4. **Account Security**

---

## 4. MODULE 1: HOMEPAGE — PROJECT / TASK-BASED WORKSPACE
After logging in, the user lands on the **Task Hub**, allowing step-by-step, multi-tasking payroll processing with full draft persistence:
- **Create New Task Modal:**
  - Select **Task Type:** `SALARY` or `INSENTIF`.
  - Select **Target Payroll Month & Year** (e.g., `Desember 2026`, `Januari 2027`, `Februari 2027`).
  - **Automatic Working Period Mapping:**
    - If `SALARY` is selected: Period is strictly **21st of (Month - 2) to 20th of (Month - 1)**.
      - *Examples:*
        - Salary Desember 2026 = Working dates **21 Oktober 2026 – 20 November 2026**.
        - Salary Januari 2027 = Working dates **21 November 2026 – 20 Desember 2026**.
        - Salary Februari 2027 = Working dates **21 Desember 2026 – 20 Januari 2027**.
    - If `INSENTIF` is selected: Period is strictly **1st to End of Month (28/29/30/31) of (Month - 1)**.
      - *Examples:*
        - Insentif Desember 2026 = Omzet dates **1 November 2026 – 30 November 2026**.
        - Insentif Januari 2027 = Omzet dates **1 Desember 2026 – 31 Desember 2026**.
        - Insentif Februari 2027 = Omzet dates **1 Januari 2027 – 31 Januari 2027**.
- **Task State & Visual Cards:**
  - Tasks can be saved at any stage (`Save Draft` / Auto-save). The user can leave a task mid-way, create or work on another task (e.g., switching between Salary Des 2026 and Insentif Des 2026), and resume anytime without losing state.
  - **Status Badges & Card Colors:**
    - `IN PROGRESS / DRAFT`: Amber/Blue card style.
    - `FINISHED / COMPLETED`: When the user clicks the **"Finish Task"** button, the task card turns **Vibrant Green**, locks accidental edits (with an explicit *"Re-open Task"* button if revisions are needed later), and feeds its final numbers into the Executive & Annual Dashboard.

---

## 5. MODULE 2: MASTER DATA & CONFIGURABLE CONSTANTS
Provide a clean CRUD interface (with pre-seeded sample data) for:
1. **Master Outlets & Salary Constants:**
   - Fields: `outlet_code` (e.g., TP-1, TP-2, ITC-UG, DELTA, BG-L2, PA-1, R-1, WTC, KAZA, SNCT, etc.), `outlet_name`, `is_active`.
   - **4 Salary Constants per Outlet:**
     - `gaji_pokok` (Monthly Base Salary, e.g., 350000 or 325000)
     - `uang_makan_utama` (Daily Main Meal Allowance / AMK, e.g., 30000, 45000, 21500, etc.)
     - `uang_makan_tambahan` (Daily Additional Product Meal Allowance, default 0, editable)
     - `uang_pulsa` (Monthly Phone Credit Allowance, e.g., 10000)
2. **Outlet Alias & Typo Mapping Dictionary:**
   - Stores known dirty aliases mapped to official `outlet_code` (e.g., `delta`, `ELTA` -> `DELTA`; `WT` -> `WTC`; `ptc` -> `PTC`). When users resolve a new typo during upload, give an option to save it permanently to this dictionary.
3. **Master SPG:**
   - Fields: `spg_name`, `assigned_outlet_id` (**Strict Rule: 1 SPG is permanently assigned to 1 specific Outlet**), `is_active`, and `aliases` (for name typo matching).
4. **Master Incentive Constants (Global Settings):**
   - `threshold_amount`: Default `5000000` (Rp 5.000.000)
   - `tier_1_rate_above_threshold`: Default `1.25` (%) -> applied to the first Rp 5.000.000 when total omzet exceeds Rp 5.000.000 (equals Rp 62.500).
   - `tier_2_rate_remaining`: Default `2.5` (%) -> applied to the remaining omzet above Rp 5.000.000.
   - `below_threshold_flat_rate`: Default `2.5` (%) -> applied to total omzet if total omzet is $\le$ Rp 5.000.000.

---

## 6. MODULE 3: SMART EXCEL UPLOAD, ANOMALY ANALYZER & PIVOT MATRIX
Inside each Task (`SALARY` or `INSENTIF`), guide the user through a seamless workflow:

### Step 1: Period Configuration (Multiplier Setup for SALARY Tasks Only)
- If the task is `SALARY`, display the calendar grid for the 21st–20th date range.
- Allow the user to set the **Daily Multiplier** for each date:
  - `1x` (Normal Day - Default)
  - `2x` ("BESAR" / Sundays & National Holidays - Provide a quick button to auto-mark all Sundays as `2x`)
  - `3x` (Eid al-Fitr / Special Major Holidays)
- *(Note: `INSENTIF` tasks have NO date multipliers; they use pure omzet).*

### Step 2: Upload 1 Excel File with 2 Sheets (Presensi & Omzet)
- User uploads the conventional `.xlsx` / `.xls` file containing 2 sheets:
  1. **Sheet Presensi:** 2D Matrix where Rows = SPG Names, Columns = Dates, and Cell Values = Outlet Codes where the SPG worked on that date (empty or `-` means off/absent).
  2. **Sheet Omzet:** 2D Matrix containing daily sales figures across dates.

### Step 3: Smart Anomaly & Typo Analyzer (Pre-Calculation Validation Screen)
Before finalizing the matrix, parse the sheets and present an interactive **Validation & Anomaly Resolution Panel**:
- **Case-Insensitive & Fuzzy Outlet Matching:** Detect typos like `delta`, `ELTA`, `WT`, `ptc` and suggest mapping them to official Master Outlets (`DELTA`, `WTC`, `PTC`).
- **Multi-Code / Special Notes in a Single Cell:** Detect cells like `ITC-UG lip` or `DELTA TP-1`.
  - **Strict Business Rule for Double Codes/Shifts in Salary:** Automatically evaluate all recognized outlet codes in that cell and **take the HIGHEST constant value** among them for that day.
- **Unrecognized SPG Names:** Allow quick mapping to existing Master SPG or 1-click creation of a new Master SPG.
- **Dirty Numeric Formatting in Omzet Sheet:** Detect malformed numbers from legacy manual typing (e.g., missing trailing zeros like `745.00` or `2.174.0`, currency symbols like `$10.000`, or two numbers separated by space in one cell like `2.148.000 3.012.000`). Flag these cells clearly so the user can click to fix, sum, or normalize them to thousands (`* 1000`).
- **AMK + AMKopi Auto-Summation (Strictly for INSENTIF Tasks Only):**
  - In the Omzet sheet, an outlet/SPG may have a main row (e.g., `R-1` / AMK) and a coffee row (e.g., `AMKOpi R-1`).
  - For **INSENTIF** calculations, automatically pair and sum the daily `AMK` omzet + `AMKopi` omzet on the same date into a single unified daily omzet for that SPG (e.g., if on Nov 15 Mega at R-1 has AMK = Rp 1.000.000 and AMKopi R-1 = Rp 200.000, Mega's unified omzet for Nov 15 is **Rp 1.200.000**).
  - Do NOT apply AMKopi omzet rules to the SALARY module.

### Step 4: Interactive Horizontal Pivot Matrix Grid (Inline Editable)
- Transform the validated data into an interactive, Excel-like horizontal Pivot Matrix table on the web:
  - **Rows:** SPG Name & Assigned Outlet.
  - **Columns:** Horizontal dates (`21..20` for Salary; `1..31` for Insentif) followed on the right by the breakdown calculation columns and final totals.
- Every cell (attendance status/outlet code, daily multiplier badge, or daily omzet amount including AMK + AMKopi breakdown) is **inline-editable** with instant reactive recalculation.

---

## 7. MODULE 4: CORE CALCULATION ENGINES

### A. SALARY Calculation Engine (Period: 21st – 20th)
Since **1 SPG = 1 Fixed Outlet**, pull the 4 constants from the SPG's assigned outlet (unless a specific cell had a multi-outlet code where the highest constant rule applies):
1. **Total Hari Masuk:** Count of active working days in the 21–20 period.
2. **Uang Makan Harian Utama:**
   $$\sum_{\text{date}=21}^{20} (\text{Present} \times \text{Outlet Uang Makan Utama} \times \text{Date Multiplier } [1, 2, \text{ or } 3])$$
3. **Uang Makan Harian Produk Tambahan:**
   $$\sum_{\text{date}=21}^{20} (\text{Present} \times \text{Outlet Uang Makan Tambahan} \times \text{Date Multiplier } [1, 2, \text{ or } 3])$$
4. **Gaji Pokok (Base Salary with 40% Attendance Threshold & Manual Override):**
   - Standard working days benchmark = **26 days** (40% threshold = $0.40 \times 26 = 10.4$ days, or $< 40\%$ of period working days).
   - **If Attendance $\ge 40\%$:** `Gaji Pokok = 100% of Outlet Gaji Pokok`.
   - **If Attendance $< 40\%$:** System automatically flags the row and calculates the **Recommended Proportional Base Salary**:
     $$\text{Recommended Gaji Pokok} = \text{Round}\left(\frac{\text{Outlet Gaji Pokok}}{26} \times \text{Total Hari Masuk}\right)$$
   - **Flexible Policy Override Toggle:** Provide an interactive toggle/checkbox per SPG row (`"Kebijakan 100% Full"`). If checked by the user, override the proportional recommendation and grant **100% Full Outlet Gaji Pokok** even if attendance is $< 40\%$.
5. **Uang Pulsa:** Fixed monthly amount from Outlet constant.
6. **Uang Tambahan (Optional Manual Input):** Editable numeric input field at the end of each SPG row (default `0`).
7. **Total Salary SPG:**
   $$\text{Total Salary} = \text{Uang Makan Utama} + \text{Uang Makan Tambahan} + \text{Gaji Pokok Final} + \text{Uang Pulsa} + \text{Uang Tambahan Manual}$$
8. **Vertical Slip Card View (Like `image_3f51df.png`):**
   - Clicking an SPG's name opens a vertical detailed breakdown card showing the SPG name, period header (`21 NOV - 20 DEC`), vertical list of dates `21` down to `20`, the `BESAR` label on 2x/3x dates, outlet code, daily calculated meal allowance (e.g., `30.000` on normal days, `60.000` on `BESAR` days), and the bottom summary (`UANG MAKAN`, `GAJI POKOK`, `UANG PULSA`, `UANG TAMBAHAN`, and bold red `GRAND TOTAL`).

### B. INSENTIF Calculation Engine (Period: 1st – 31st)
1. **Total Omzet Bulanan SPG:**
   $$\text{Total Omzet} = \sum_{\text{date}=1}^{31} (\text{Omzet AMK}_{\text{date}} + \text{Omzet AMKopi}_{\text{date}})$$
   *(Pure omzet, no date multipliers).*
2. **Tiered Incentive Formula:**
   - **If $\text{Total Omzet} > \text{Rp } 5.000.000$:**
     - Tier 1 (First Rp 5.000.000): $1.25\% \times 5.000.000 = \text{Rp } 62.500$
     - Tier 2 (Remaining Omzet $A$): $2.5\% \times (\text{Total Omzet} - 5.000.000)$
     - $\text{Insentif Murni} = 62.500 + A$
     - *(Test Case 1: Mega with Rp 22.500.000 -> $62.500 + 2.5\% \times 17.500.000 (437.500) = \text{Rp } 500.000$).*
   - **If $\text{Total Omzet} \le \text{Rp } 5.000.000$:**
     - $\text{Insentif Murni} = 2.5\% \times \text{Total Omzet}$
     - *(Test Case 2: Fitri with Rp 3.500.000 -> $2.5\% \times 3.500.000 = \text{Rp } 87.500$).*
3. **Uang Tambahan Insentif (Optional Manual Input):** Editable numeric input field at the end of each SPG row (default `0`).
4. **Total Insentif Akhir:**
   $$\text{Total Insentif} = \text{Insentif Murni} + \text{Uang Tambahan Manual}$$

---

## 8. MODULE 5: EXECUTIVE DASHBOARD, MULTI-FILTER & HORIZONTAL MATRIX EXCEL EXPORT
1. **Flexible Element Filtering:**
   - Filter by **Single SPG**, **Multi-Select SPGs** (compare multiple selected SPGs side-by-side), **By Outlet**, or **All SPGs**.
   - Filter by **Component View:** `Salary Only`, `Insentif Only`, or `Combined Monthly Take-Home Pay (Salary + Insentif)`.
2. **Annual & Custom Period Financial Recapitulation:**
   - Select a Year (e.g., `2026`, `2027`) or a custom month-to-month range:
     - **Company Expense Summary Cards & Monthly Trend Table:**
       - Total Monthly Salary Expense (per month & cumulative period total).
       - Total Monthly Incentive Expense (per month & cumulative period total).
       - Grand Total Payroll Outflow (`Salary + Insentif` per month & full-year accumulation).
     - **Annual SPG Earnings Matrix:**
       - Rows = SPG Names, Columns = Months (`Jan` through `Dec`) + `Annual Total per SPG`, showing each SPG's complete earnings trajectory across the year.
3. **Horizontal Pivot Matrix Excel Export (`.xlsx`):**
   - Provide export buttons on all views (Salary Matrix, Incentive Matrix, Base Components, and Annual Recap).
   - Exported Excel files MUST preserve the **Horizontal Pivot Matrix layout** (Columns = Dates running horizontally from left to right, followed by the breakdown calculation columns and Grand Total on the right, formatted cleanly with currency formatting and frozen left columns).

---

## 9. DELIVERABLES & IMPLEMENTATION INSTRUCTIONS
1. Generate the complete **Database Schema & Migrations** (`users`, `outlets`, `outlet_aliases`, `spgs`, `incentive_settings`, `payroll_tasks`, `task_date_multipliers`, `salary_records`, `salary_daily_logs`, `incentive_records`, `incentive_daily_logs`).
2. Create a rich **DatabaseSeeder** pre-populated with realistic sample outlets (`TP-1`, `TP-2`, `ITC-UG`, `DELTA`, `BG-L2`, `PA-1`, `R-1`, `WTC`, `KAZA`, `SNCT`) with their constants, sample SPGs (`Mega`, `Fitri`, `Nadila`, etc.), and default admin credentials (`admin@payroll.local` / `password123`).
3. Implement all **Controllers, Services (`ExcelMatrixParserService`, `SalaryCalculatorService`, `IncentiveCalculatorService`, `MatrixExcelExportService`), and Blade + Alpine/Livewire views** completely—no placeholders or TODO comments.