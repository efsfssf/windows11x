# OFFLINE ONLY - never installs, loads, or registers the schema.
# Usage: .\Verify-Offline-APISet.ps1
param([string]$SchemaPath = (Join-Path $PSScriptRoot 'ApiSetSchema-Windows11X-26100-OFFLINE-UNSIGNED.dll'))
$ErrorActionPreference = 'Stop'
$SchemaPath = (Resolve-Path -LiteralPath $SchemaPath).Path
$script:bytes = [System.IO.File]::ReadAllBytes($SchemaPath)
$expectedHash = '75FB8F895EA030C60F435863971EEB025798ABDC306958B593DBE6EB185C0BBD'
$hash = (Get-FileHash -LiteralPath $SchemaPath -Algorithm SHA256).Hash
if ($hash -ne $expectedHash) { throw "Unexpected experimental DLL SHA256: $hash" }
function U32([int]$offset) { [BitConverter]::ToUInt32($script:bytes,$offset) }
function U16([int]$offset) { [BitConverter]::ToUInt16($script:bytes,$offset) }
function U8([int]$offset) { $script:bytes[$offset] }
function Str16([int]$offset,[int]$length) {
    if ($length -lt 0 -or ($length % 2) -ne 0 -or $offset -lt 0 -or ($offset+$length) -gt $script:bytes.Length) { throw 'Invalid string bounds' }
    [System.Text.Encoding]::Unicode.GetString($script:bytes,$offset,$length)
}
$ns=0x2000
if ((U32 $ns) -ne 6 -or (U8 ($ns+0x1c)) -ne 7) { throw 'Unexpected API Set profile' }
if ((U32 ($ns+4)) -ne (U32 ($ns+0x20))) { throw 'V6/V7 sizes mismatch' }
if ((U32 ($ns+0xc)) -ne 984) { throw 'Unexpected legacy contract count' }
$entries = U32 ($ns+0x10)
$pool = U32 ($ns+0x40)
$familyStart = U32 ($ns+0x48)
$hostStart = U32 ($ns+0x78)
$hostCount = U16 ($ns+0x7c)
if ($hostCount -ne 288) { throw "Unexpected host count: $hostCount" }
$tests=@(
    [pscustomobject]@{ Name='ext-ms-win-ntos-stateseparation-l1-1-0'; Family=647; Expected='statesepext.sys' },
    [pscustomobject]@{ Name='ext-ms-win-core-stateseparationext-l1-1-0'; Family=447; Expected='onecorestateseparationext.dll' }
)
$results = foreach($t in $tests) {
    $legacy = $null
    for($i=0; $i -lt 984; $i++) {
        $entry=$ns+$entries+$i*24
        $name=Str16 ($ns + (U32 ($entry+4))) (U32 ($entry+8))
        if($name -eq $t.Name) {
            $value=$ns+(U32 ($entry+16))
            $legacy=[pscustomobject]@{ Host=(Str16 ($ns+(U32 ($value+12))) (U32 ($value+16))); Flags=(U32 $entry) }
            break
        }
    }
    if(-not $legacy) { throw "Missing legacy contract $($t.Name)" }
    $family=$ns+$familyStart+$t.Family*12
    $hostIndex=U16 ($family+4)
    $flags=U8 ($family+10)
    $hostRecord=$ns+$hostStart+$hostIndex*12
    $v7=Str16 ($ns+$pool+(U32 ($hostRecord+4))) ((U16 ($hostRecord+8))*2)
    if ($legacy.Host -ne $t.Expected -or $v7 -ne $t.Expected -or $legacy.Flags -ne 1 -or (($flags -band 2) -eq 0)) {
        throw "Offline mapping assertion failed for $($t.Name)"
    }
    [pscustomobject]@{ Contract=$t.Name; V6Host=$legacy.Host; V7Host=$v7; SealedV6=($legacy.Flags -eq 1); V7DefaultHostPresent=(($flags -band 2) -ne 0); V7HostIndex=$hostIndex }
}
Write-Host 'OFFLINE FORMAT CHECK: PASS (not a boot/trust validation)'
Write-Host "SHA256: $hash"
Write-Host "Authenticode status: $((Get-AuthenticodeSignature -LiteralPath $SchemaPath).Status)"
$results | Format-List
Write-Warning 'Modified PE is not trusted for Windows early boot. Do NOT overwrite C:\Windows\System32\ApiSetSchema.dll or set BaseApiSetSchema with this file.'
