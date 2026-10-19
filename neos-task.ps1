# Einmal-Aufgabe (Interactive, Administrator): Composer-Grundgeruest holen (nur beim ersten Mal), Image bauen, starten
$ErrorActionPreference = 'Continue'
$log = 'D:\stacks\neos\neos-task.log'
function L($m) { "$(Get-Date -Format 'HH:mm:ss') $m" | Out-File $log -Append -Encoding utf8 }
Set-Location D:\stacks\neos
L 'start'
if (-not (Test-Path D:\stacks\neos\composer.lock)) {
  L 'bootstrap: neos-base-distribution (composer.json/lock, Configuration)'
  $sh = 'set -e; cd /tmp; composer create-project --no-install --no-scripts --no-interaction neos/neos-base-distribution n; cd n; composer config platform.php 8.4.99; composer update --no-install --no-scripts --no-interaction --ignore-platform-req=ext-*; cp composer.json composer.lock /work/; cp -rn Configuration/. /work/Configuration/; [ -d DistributionPackages ] && cp -rn DistributionPackages/. /work/DistributionPackages/ || true; ls -la /work'
  docker run --rm -v D:/stacks/neos:/work composer:2 sh -c $sh 2>&1 | ForEach-Object { "$_" } | Out-File $log -Append -Encoding utf8
  if (-not (Test-Path D:\stacks\neos\composer.lock)) { L 'BOOTSTRAP FEHLER'; L 'ende'; exit 1 }
}
L 'build'
docker compose build --pull 2>&1 | ForEach-Object { "$_" } | Out-File $log -Append -Encoding utf8
if ($LASTEXITCODE -ne 0) { L "BUILD FEHLER $LASTEXITCODE"; L 'ende'; exit 1 }
L 'up'
docker compose up -d 2>&1 | ForEach-Object { "$_" } | Out-File $log -Append -Encoding utf8
L "up exit $LASTEXITCODE"
L 'ende'
