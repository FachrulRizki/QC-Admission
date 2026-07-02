import re

path = r'c:\laragon\www\qc_admision\resources\js\src\pages\dashboard.vue'

with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Replace hardcoded #F4F7F6 page bg with CSS var
content = content.replace(
    '.db-page { background: #F4F7F6; min-height: 100vh; padding: 16px; }',
    '''.db-page {
  background: rgb(var(--v-theme-background));
  color: rgb(var(--v-theme-on-background));
  min-height: 100vh; padding: 16px;
}'''
)

# 2. Replace hardcoded topbar bg
content = content.replace(
    '  background: #fff;\n  border: 1px solid #E1E7E5;\n  border-radius: 12px;\n  padding: 12px 20px;\n  box-shadow: 0 1px 4px rgba(16,24,22,0.06);\n  margin-bottom: 8px;\n  flex-wrap: wrap;',
    '  background: rgb(var(--v-theme-surface));\n  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));\n  border-radius: 12px;\n  padding: 12px 20px;\n  box-shadow: 0 1px 4px rgba(16,24,22,0.06);\n  margin-bottom: 8px;\n  flex-wrap: wrap;'
)

# 3. Replace hardcoded text colors in topbar
content = content.replace('.db-topbar__title-main { font-size: 0.9rem; font-weight: 700; color: #1A1F1E; }',
    '.db-topbar__title-main { font-size: 0.9rem; font-weight: 700; color: rgb(var(--v-theme-on-surface)); }')
content = content.replace('color: #1A1F1E; font-size: 0.8rem; font-weight: 600;',
    'color: rgb(var(--v-theme-on-surface)); font-size: 0.8rem; font-weight: 600;')
content = content.replace('.db-kpi-card__label { font-size: 0.7rem; color: #5C6B67;',
    '.db-kpi-card__label { font-size: 0.7rem; color: rgba(var(--v-theme-on-surface),0.6);')
content = content.replace('.db-kpi-card__value { font-size: 1.5rem; font-weight: 800; color: #1A1F1E;',
    '.db-kpi-card__value { font-size: 1.5rem; font-weight: 800; color: rgb(var(--v-theme-on-surface));')
content = content.replace('  background: #fff; border: 1.5px solid var(--qc-green); border-radius: 8px;',
    '  background: rgb(var(--v-theme-surface)); border: 1.5px solid var(--qc-green); border-radius: 8px;')
content = content.replace('  background: #fff; border: 1px solid #E1E7E5; border-radius: 12px;',
    '  background: rgb(var(--v-theme-surface)); border: 1px solid rgba(var(--v-border-color),var(--v-border-opacity)); border-radius: 12px;')
content = content.replace('.fp {\n  background: #fff;\n  border: 1px solid #E1E7E5;',
    '.fp {\n  background: rgb(var(--v-theme-surface));\n  border: 1px solid rgba(var(--v-border-color),var(--v-border-opacity));')
content = content.replace('.fp__input {\n  width: 100%;\n  border: 1.5px solid #E1E7E5;\n  border-radius: 10px;\n  padding: 8px 10px;\n  font-size: 0.85rem;\n  color: #1A1F1E;\n  background: #fff;',
    '.fp__input {\n  width: 100%;\n  border: 1.5px solid rgba(var(--v-border-color),var(--v-border-opacity));\n  border-radius: 10px;\n  padding: 8px 10px;\n  font-size: 0.85rem;\n  color: rgb(var(--v-theme-on-surface));\n  background: rgb(var(--v-theme-surface));')
content = content.replace('.db-date-input {\n  border: 1.5px solid #E1E7E5; border-radius: 8px; padding: 5px 10px;\n  font-size: 0.82rem; color: #1A1F1E; outline: none;',
    '.db-date-input {\n  border: 1.5px solid rgba(var(--v-border-color),var(--v-border-opacity)); border-radius: 8px; padding: 5px 10px;\n  font-size: 0.82rem; color: rgb(var(--v-theme-on-surface)); background: rgb(var(--v-theme-surface)); outline: none;')
content = content.replace('.db-date-panel {\n  position: absolute; top: calc(100% + 6px); right: 0;\n  background: #fff; border: 1px solid #E1E7E5;',
    '.db-date-panel {\n  position: absolute; top: calc(100% + 6px); right: 0;\n  background: rgb(var(--v-theme-surface)); border: 1px solid rgba(var(--v-border-color),var(--v-border-opacity));')
content = content.replace('.db-refresh-btn {\n  width: 36px; height: 36px; border-radius: 50%;\n  border: 1.5px solid var(--qc-green); background: #fff;',
    '.db-refresh-btn {\n  width: 36px; height: 36px; border-radius: 50%;\n  border: 1.5px solid var(--qc-green); background: rgb(var(--v-theme-surface));')

# 4. Replace ds-card hardcoded colors in global style
content = content.replace(
    '  background: #FFFFFF !important;\n  border: 1px solid #E1E7E5 !important;',
    '  background: rgb(var(--v-theme-surface)) !important;\n  border: 1px solid rgba(var(--v-border-color),var(--v-border-opacity)) !important;'
)
content = content.replace(
    '  border-bottom: 1px solid #E1E7E5;\n  background: #fff;',
    '  border-bottom: 1px solid rgba(var(--v-border-color),var(--v-border-opacity));\n  background: rgb(var(--v-theme-surface));'
)
content = content.replace('.ds-card-title { font-size: 0.85rem; color: #1A1F1E; }',
    '.ds-card-title { font-size: 0.85rem; color: rgb(var(--v-theme-on-surface)); }')
content = content.replace('.fp__title {\n  font-size: 0.875rem;\n  font-weight: 700;\n  color: #1A1F1E;',
    '.fp__title {\n  font-size: 0.875rem;\n  font-weight: 700;\n  color: rgb(var(--v-theme-on-surface));')
content = content.replace('.db-subtitle-bar__time {\n  display: flex; align-items: center; gap: 5px;\n  font-size: 0.78rem; color: #5C6B67;\n}',
    '.db-subtitle-bar__time {\n  display: flex; align-items: center; gap: 5px;\n  font-size: 0.78rem; color: rgba(var(--v-theme-on-surface),0.6);\n}')
content = content.replace('.db-subtitle-bar__range { font-size: 0.78rem; color: #5C6B67; }',
    '.db-subtitle-bar__range { font-size: 0.78rem; color: rgba(var(--v-theme-on-surface),0.6); }')

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Dashboard dark mode fix applied.")
