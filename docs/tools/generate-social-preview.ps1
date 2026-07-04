<#
Generates docs/assets/images/social-preview.png (1200x630) for LinkedIn/Open Graph/Twitter
link previews, using only built-in .NET System.Drawing APIs (no external dependencies).

Safe to re-run: it always overwrites the same output file.

Usage (from repo root or anywhere):
    powershell -ExecutionPolicy Bypass -File docs/tools/generate-social-preview.ps1
#>

Add-Type -AssemblyName System.Drawing

$ErrorActionPreference = "Stop"

$outDir  = Join-Path $PSScriptRoot "..\assets\images"
$outDir  = [System.IO.Path]::GetFullPath($outDir)
$outFile = Join-Path $outDir "social-preview.png"

if (-not (Test-Path $outDir)) {
    New-Item -ItemType Directory -Path $outDir -Force | Out-Null
}

$width  = 1200
$height = 630

# Theme colors (matches resources/css/app.css dark cybersecurity theme)
$colBg1         = [System.Drawing.Color]::FromArgb(255, 13, 19, 29)    # #0d131d
$colBg2         = [System.Drawing.Color]::FromArgb(255, 21, 31, 46)    # #151f2e
$colSurfaceMuted= [System.Drawing.Color]::FromArgb(255, 27, 38, 55)    # #1b2637
$colPrimary     = [System.Drawing.Color]::FromArgb(255, 77, 171, 247)  # #4dabf7
$colPrimaryDark = [System.Drawing.Color]::FromArgb(255, 116, 192, 252) # #74c0fc
$colText        = [System.Drawing.Color]::FromArgb(255, 231, 237, 245) # #e7edf5
$colMuted       = [System.Drawing.Color]::FromArgb(255, 154, 168, 187) # #9aa8bb
$colBorder      = [System.Drawing.Color]::FromArgb(255, 43, 57, 77)    # #2b394d

function New-RoundedRectPath {
    param([float]$x, [float]$y, [float]$w, [float]$h, [float]$radius)
    $path = New-Object System.Drawing.Drawing2D.GraphicsPath
    $d = $radius * 2
    $path.AddArc($x, $y, $d, $d, 180, 90)
    $path.AddArc(($x + $w - $d), $y, $d, $d, 270, 90)
    $path.AddArc(($x + $w - $d), ($y + $h - $d), $d, $d, 0, 90)
    $path.AddArc($x, ($y + $h - $d), $d, $d, 90, 90)
    $path.CloseFigure()
    return $path
}

function Get-FitFontSize {
    param($graphics, [string]$text, [string]$fontFamily, [System.Drawing.FontStyle]$style, [float]$startSize, [float]$maxWidth, [float]$minSize = 14)
    $size = $startSize
    while ($size -gt $minSize) {
        $font = New-Object System.Drawing.Font($fontFamily, $size, $style, [System.Drawing.GraphicsUnit]::Pixel)
        $measured = $graphics.MeasureString($text, $font)
        if ($measured.Width -le $maxWidth) {
            return $font
        }
        $font.Dispose()
        $size -= 1
    }
    return New-Object System.Drawing.Font($fontFamily, $minSize, $style, [System.Drawing.GraphicsUnit]::Pixel)
}

$bmp = New-Object System.Drawing.Bitmap $width, $height
$g = [System.Drawing.Graphics]::FromImage($bmp)
$g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
$g.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit
$g.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality

# --- Background gradient ---
$fullRect = New-Object System.Drawing.Rectangle 0, 0, $width, $height
$bgBrush = New-Object System.Drawing.Drawing2D.LinearGradientBrush($fullRect, $colBg1, $colBg2, 35)
$g.FillRectangle($bgBrush, $fullRect)
$bgBrush.Dispose()

# --- Soft glow orbs ---
function Draw-Glow {
    param($g, [float]$cx, [float]$cy, [float]$maxR, [System.Drawing.Color]$color, [int]$peakAlpha = 26)
    $steps = 18
    for ($i = $steps; $i -gt 0; $i--) {
        $r = $maxR * ($i / $steps)
        $alpha = [Math]::Max(1, [int]($peakAlpha * (1 - ($i / $steps))))
        $c = [System.Drawing.Color]::FromArgb($alpha, $color)
        $b = New-Object System.Drawing.SolidBrush $c
        $g.FillEllipse($b, ($cx - $r), ($cy - $r), ($r * 2), ($r * 2))
        $b.Dispose()
    }
}
Draw-Glow $g 1040 60 300 $colPrimary 30
Draw-Glow $g 40 640 260 $colPrimaryDark 22

# --- Faint grid pattern (top-right), mirrors the site hero background ---
$gridPen = New-Object System.Drawing.Pen ([System.Drawing.Color]::FromArgb(10, 255, 255, 255)), 1
for ($x = 640; $x -lt $width; $x += 40) {
    $g.DrawLine($gridPen, $x, 0, $x, 300)
}
for ($y = 0; $y -lt 300; $y += 40) {
    $g.DrawLine($gridPen, 640, $y, $width, $y)
}
$gridPen.Dispose()

# --- Outer border + left accent bar ---
$borderPen = New-Object System.Drawing.Pen $colBorder, 2
$g.DrawRectangle($borderPen, 1, 1, ($width - 3), ($height - 3))
$borderPen.Dispose()

$accentBrush = New-Object System.Drawing.SolidBrush $colPrimary
$g.FillRectangle($accentBrush, 0, 0, 10, $height)
$accentBrush.Dispose()

# --- Badge icon (shield/lock mark, consistent with the site brand mark) ---
$badgeX = 96.0; $badgeY = 64.0; $badgeSize = 92.0
$badgePath = New-RoundedRectPath -x $badgeX -y $badgeY -w $badgeSize -h $badgeSize -radius 22
$badgeBgBrush = New-Object System.Drawing.SolidBrush $colSurfaceMuted
$g.FillPath($badgeBgBrush, $badgePath)
$badgeBgBrush.Dispose()
$badgeBorderPen = New-Object System.Drawing.Pen ([System.Drawing.Color]::FromArgb(90, $colPrimary)), 2
$g.DrawPath($badgeBorderPen, $badgePath)
$badgeBorderPen.Dispose()
$badgePath.Dispose()

# Lock glyph inside badge
$lockCx = $badgeX + ($badgeSize / 2)
$lockShacklePen = New-Object System.Drawing.Pen $colPrimaryDark, 7
$lockShacklePen.StartCap = [System.Drawing.Drawing2D.LineCap]::Round
$lockShacklePen.EndCap = [System.Drawing.Drawing2D.LineCap]::Round
$shackleRect = New-Object System.Drawing.RectangleF (($lockCx - 19), ($badgeY + 20), 38, 34)
$g.DrawArc($lockShacklePen, $shackleRect, 180, 180)
$lockShacklePen.Dispose()

$bodyPath = New-RoundedRectPath -x ($lockCx - 26) -y ($badgeY + 40) -w 52 -h 38 -radius 8
$bodyBrush = New-Object System.Drawing.SolidBrush $colPrimaryDark
$g.FillPath($bodyBrush, $bodyPath)
$bodyBrush.Dispose()
$bodyPath.Dispose()

$keyholeBrush = New-Object System.Drawing.SolidBrush $colBg1
$g.FillEllipse($keyholeBrush, ($lockCx - 5), ($badgeY + 54), 10, 10)
$g.FillRectangle($keyholeBrush, ($lockCx - 3), ($badgeY + 60), 6, 10)
$keyholeBrush.Dispose()

# --- Eyebrow pill ---
# Built from char codes (not literal unicode) so rendering is correct regardless
# of the script file's saved encoding vs. the console/system codepage.
$middot = [char]0x00B7
$eyebrowText = "PORTFOLIO PROJECT  $middot  LARAVEL 12"
$eyebrowFont = New-Object System.Drawing.Font("Segoe UI Semibold", 18, [System.Drawing.FontStyle]::Bold, [System.Drawing.GraphicsUnit]::Pixel)
$eyebrowSize = $g.MeasureString($eyebrowText, $eyebrowFont)
$eyebrowPadX = 18.0; $eyebrowPadY = 10.0
$eyebrowX = 96.0; $eyebrowY = 180.0
$eyebrowPath = New-RoundedRectPath -x $eyebrowX -y $eyebrowY -w ($eyebrowSize.Width + $eyebrowPadX * 2) -h ($eyebrowSize.Height + $eyebrowPadY * 2) -radius 18
$eyebrowBgBrush = New-Object System.Drawing.SolidBrush ([System.Drawing.Color]::FromArgb(60, $colPrimary))
$g.FillPath($eyebrowBgBrush, $eyebrowPath)
$eyebrowBgBrush.Dispose()
$eyebrowBorderPen = New-Object System.Drawing.Pen ([System.Drawing.Color]::FromArgb(110, $colPrimary)), 1.5
$g.DrawPath($eyebrowBorderPen, $eyebrowPath)
$eyebrowBorderPen.Dispose()
$eyebrowPath.Dispose()
$eyebrowTextBrush = New-Object System.Drawing.SolidBrush $colPrimaryDark
$g.DrawString($eyebrowText, $eyebrowFont, $eyebrowTextBrush, ($eyebrowX + $eyebrowPadX), ($eyebrowY + $eyebrowPadY))
$eyebrowTextBrush.Dispose()
$eyebrowFont.Dispose()

# --- Title (two lines) ---
$titleFont = New-Object System.Drawing.Font("Segoe UI", 60, [System.Drawing.FontStyle]::Bold, [System.Drawing.GraphicsUnit]::Pixel)
$titleBrush = New-Object System.Drawing.SolidBrush $colText
$titleX = 96.0
$titleY1 = 250.0
$titleY2 = 250.0 + 72.0
$g.DrawString("Cyber Security Incident", $titleFont, $titleBrush, $titleX, $titleY1)
$g.DrawString("Management", $titleFont, $titleBrush, $titleX, $titleY2)
$titleBrush.Dispose()
$titleFont.Dispose()

# --- Subtitle ---
$subtitleFont = New-Object System.Drawing.Font("Segoe UI Semibold", 34, [System.Drawing.FontStyle]::Bold, [System.Drawing.GraphicsUnit]::Pixel)
$subtitleBrush = New-Object System.Drawing.SolidBrush $colPrimaryDark
$subtitleY = $titleY2 + 88.0
$g.DrawString("Laravel 12 Portfolio Project", $subtitleFont, $subtitleBrush, $titleX, $subtitleY)
$subtitleBrush.Dispose()
$subtitleFont.Dispose()

# --- Feature line (auto-fit width) ---
$bullet = [char]0x2022
$featureText = "Incident Reporting  $bullet  IOC Tracking  $bullet  Audit Logs  $bullet  Security Reports"
$maxFeatureWidth = $width - ($titleX * 2)
$featureFont = Get-FitFontSize -graphics $g -text $featureText -fontFamily "Segoe UI" -style ([System.Drawing.FontStyle]::Regular) -startSize 28 -maxWidth $maxFeatureWidth -minSize 18
$featureBrush = New-Object System.Drawing.SolidBrush $colMuted
$featureY = $subtitleY + 62.0
$g.DrawString($featureText, $featureFont, $featureBrush, $titleX, $featureY)
$featureBrush.Dispose()
$featureFont.Dispose()

# --- Footer divider + repo line ---
$footerLineY = 556.0
$dividerPen = New-Object System.Drawing.Pen $colBorder, 1.5
$g.DrawLine($dividerPen, $titleX, $footerLineY, ($width - $titleX), $footerLineY)
$dividerPen.Dispose()

$footerFont = New-Object System.Drawing.Font("Segoe UI", 20, [System.Drawing.FontStyle]::Regular, [System.Drawing.GraphicsUnit]::Pixel)
$footerBrush = New-Object System.Drawing.SolidBrush $colMuted
$g.DrawString("github.com/farhanrahmananik/cyber-security-incident-management-laravel", $footerFont, $footerBrush, $titleX, ($footerLineY + 22))
$footerBrush.Dispose()
$footerFont.Dispose()

$footerNameFont = New-Object System.Drawing.Font("Segoe UI Semibold", 20, [System.Drawing.FontStyle]::Bold, [System.Drawing.GraphicsUnit]::Pixel)
$footerNameText = "Farhan Rahman Anik"
$footerNameSize = $g.MeasureString($footerNameText, $footerNameFont)
$footerNameBrush = New-Object System.Drawing.SolidBrush $colText
$g.DrawString($footerNameText, $footerNameFont, $footerNameBrush, ($width - $titleX - $footerNameSize.Width), ($footerLineY + 22))
$footerNameBrush.Dispose()
$footerNameFont.Dispose()

# --- Save ---
if (Test-Path $outFile) {
    Remove-Item $outFile -Force
}
$bmp.Save($outFile, [System.Drawing.Imaging.ImageFormat]::Png)

$g.Dispose()
$bmp.Dispose()

Write-Host "Generated: $outFile"
