$files = @(
    "resources\js\src\pages\dashboard.vue",
    "resources\js\src\views\qc-admission\dashboard\DashboardRecentQCTable.vue",
    "resources\js\src\views\qc-admission\dashboard\DashboardMatrixTable.vue",
    "resources\js\src\views\qc-admission\dashboard\DashboardAvgTable.vue",
    "resources\js\src\views\qc-admission\dashboard\DashboardEdukasiPerPetugas.vue",
    "resources\js\src\views\qc-admission\dashboard\DashboardFunnelChart.vue",
    "resources\js\src\views\qc-admission\dashboard\DashboardBarChart.vue"
)

foreach ($f in $files) {
    $path = "c:\laragon\www\qc_admision\$f"
    if (Test-Path $path) {
        $content = Get-Content $path -Raw -Encoding UTF8
        $content = $content -replace '#00B37E', 'var(--qc-green)'
        $content = $content -replace '#00C896', 'var(--qc-green)'
        $content = $content -replace '#009E6B', 'var(--qc-green-dark)'
        $content = $content -replace '#D7F5EA', 'var(--qc-green-light)'
        $content = $content -replace '#005C42', 'var(--qc-green-dark)'
        $content = $content -replace '#007A52', 'var(--qc-green-dark)'
        $content = $content -replace '#00A87C', 'var(--qc-green)'
        $content = $content -replace '0,180,126', '14,165,233'
        $content = $content -replace '0,100,70',  '3,105,161'
        Set-Content $path -Value $content -Encoding UTF8
        Write-Host "Updated: $f"
    }
}
Write-Host "Done."
