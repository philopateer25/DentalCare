; DOXamigo-Setup.exe - native Windows installer (Phase 6).
; Built by build.ps1:  ISCC /DAppVersion=.. /DCentralUrl=.. /DStageDir=.. DOXamigo.iss
;
; New install: asks for the clinic name, the admin's email and the enrollment code,
; then runs setup\setup.ps1 (services, database, firewall, license).
; Update: same .exe; skips the questions and keeps <root>\data untouched.

#ifndef AppVersion
  #define AppVersion "dev"
#endif
#ifndef CentralUrl
  #define CentralUrl "https://central.doxamigo.com"
#endif
#ifndef StageDir
  #define StageDir "build\stage"
#endif

[Setup]
AppId={{8F1C7B52-4D3A-4E8B-9C21-6A0D5E7F1B34}
AppName=DOXamigo
AppVersion={#AppVersion}
AppPublisher=DOXamigo
DefaultDirName=C:\DOXamigo
UsePreviousAppDir=yes
DisableProgramGroupPage=yes
PrivilegesRequired=admin
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
MinVersion=10.0
OutputBaseFilename=DOXamigo-Setup-{#AppVersion}
Compression=lzma2/max
SolidCompression=yes
WizardStyle=modern
CloseApplications=no
UninstallDisplayName=DOXamigo clinic system
SetupLogging=yes

[Files]
Source: "{#StageDir}\app\*";     DestDir: "{app}\app";     Flags: ignoreversion recursesubdirs createallsubdirs
Source: "{#StageDir}\runtime\*"; DestDir: "{app}\runtime"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "{#StageDir}\setup\*";   DestDir: "{app}\setup";   Flags: ignoreversion recursesubdirs createallsubdirs

[InstallDelete]
; Old code from a previous version. Never "{app}\app" as a whole: app\storage is a
; junction into data\ (uploads, backups) and must not be followed.
Type: filesandordirs; Name: "{app}\app\app"
Type: filesandordirs; Name: "{app}\app\bootstrap\cache"
Type: filesandordirs; Name: "{app}\app\config"
Type: filesandordirs; Name: "{app}\app\database\migrations"
Type: filesandordirs; Name: "{app}\app\lang"
Type: filesandordirs; Name: "{app}\app\public\build"
Type: filesandordirs; Name: "{app}\app\resources"
Type: filesandordirs; Name: "{app}\app\routes"
Type: filesandordirs; Name: "{app}\app\vendor"

[UninstallRun]
Filename: "powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\setup\uninstall.ps1"" -Root ""{app}"""; Flags: runhidden waituntilterminated; RunOnceId: "RemoveServices"

[UninstallDelete]
Type: files; Name: "{commondesktop}\DOXamigo.url"
Type: filesandordirs; Name: "{app}\services"

[Code]
type
  TMemoryStatusEx = record
    dwLength: Cardinal;
    dwMemoryLoad: Cardinal;
    ullTotalPhys: Int64;
    ullAvailPhys: Int64;
    ullTotalPageFile: Int64;
    ullAvailPageFile: Int64;
    ullTotalVirtual: Int64;
    ullAvailVirtual: Int64;
    ullAvailExtendedVirtual: Int64;
  end;

function GlobalMemoryStatusEx(var lpBuffer: TMemoryStatusEx): Boolean;
  external 'GlobalMemoryStatusEx@kernel32.dll stdcall';

var
  ClinicPage: TInputQueryWizardPage;
  ResultPage: TOutputMsgMemoWizardPage;

function IsUpgrade(): Boolean;
begin
  Result := FileExists(AddBackslash(WizardDirValue) + 'data\.env');
end;

{ Minimum PC check: warns, never blocks (old clinic PCs often still work). }
function InitializeSetup(): Boolean;
var
  Mem: TMemoryStatusEx;
  Problems: String;
  FreeBytes, TotalBytes: Int64;
  Version: TWindowsVersion;
begin
  Result := True;
  Problems := '';

  Mem.dwLength := SizeOf(Mem);
  if GlobalMemoryStatusEx(Mem) and (Mem.ullTotalPhys < Int64(3800) * 1024 * 1024) then
    Problems := Problems + '- Less than 4 GB of memory: DOXamigo may be slow.' + #13#10;

  if GetSpaceOnDisk64('C:\', FreeBytes, TotalBytes) and (FreeBytes < Int64(20) * 1024 * 1024 * 1024) then
    Problems := Problems + '- Less than 20 GB free on C: (needed for the database, photos and backups).' + #13#10;

  GetWindowsVersionEx(Version);
  if (Version.Major < 10) then
    Problems := Problems + '- Windows 10 or newer is required.' + #13#10;

  if Problems <> '' then
    Result := MsgBox('This PC is below the recommended minimum:' + #13#10#13#10 + Problems + #13#10 + 'Install anyway?', mbConfirmation, MB_YESNO) = IDYES;
end;

procedure InitializeWizard();
begin
  ClinicPage := CreateInputQueryPage(wpSelectDir,
    'Clinic details', 'Who is this installation for?',
    'The admin gets a login with a temporary password at the end of the setup. ' +
    'The enrollment code comes from DOXamigo (you can also enter it later on the License page).');
  ClinicPage.Add('Clinic name:', False);
  ClinicPage.Add('Admin email (used to log in):', False);
  ClinicPage.Add('Enrollment code (DXA-XXXX-XXXX-XXXX), optional:', False);

  ResultPage := CreateOutputMsgMemoPage(wpInstalling,
    'DOXamigo is ready', 'Write down the login and the address below.',
    'The password is shown only once.', '');
end;

function ShouldSkipPage(PageID: Integer): Boolean;
begin
  Result := (PageID = ClinicPage.ID) and IsUpgrade();
end;

function NextButtonClick(CurPageID: Integer): Boolean;
var
  Email: String;
begin
  Result := True;
  if CurPageID = ClinicPage.ID then
  begin
    Email := Trim(ClinicPage.Values[1]);
    if Trim(ClinicPage.Values[0]) = '' then
    begin
      MsgBox('Please enter the clinic name.', mbError, MB_OK);
      Result := False;
    end
    else if (Pos('@', Email) < 2) or (Pos('.', Email) = 0) then
    begin
      MsgBox('Please enter a valid email address for the admin.', mbError, MB_OK);
      Result := False;
    end;
  end;
end;

function JsonEscape(const S: String): String;
begin
  Result := S;
  StringChangeEx(Result, '\', '\\', True);
  StringChangeEx(Result, '"', '\"', True);
  StringChangeEx(Result, #13, '', True);
  StringChangeEx(Result, #10, ' ', True);
  StringChangeEx(Result, #9, ' ', True);
end;

{ Stop the services before files are replaced (php-cgi.exe / caddy.exe are in use). }
function PrepareToInstall(var NeedsRestart: Boolean): String;
var
  Code: Integer;
begin
  Result := '';
  Exec('powershell.exe', '-NoProfile -ExecutionPolicy Bypass -Command "Get-Service -Name ''DOXamigo-*'' -ErrorAction SilentlyContinue | Stop-Service -Force -ErrorAction SilentlyContinue"',
    '', SW_HIDE, ewWaitUntilTerminated, Code);
end;

procedure CurStepChanged(CurStep: TSetupStep);
var
  Params, ParamsFile, Lines, Login, Addresses: String;
  Code: Integer;
  Text: AnsiString;
begin
  if CurStep <> ssPostInstall then
    Exit;

  ParamsFile := ExpandConstant('{app}\data\install-params.json');
  ForceDirectories(ExpandConstant('{app}\data'));
  if not IsUpgrade() then
  begin
    Params := '{"clinicName":"' + JsonEscape(Trim(ClinicPage.Values[0])) + '",' +
              '"adminEmail":"' + JsonEscape(Trim(ClinicPage.Values[1])) + '",' +
              '"enrollmentCode":"' + JsonEscape(Trim(ClinicPage.Values[2])) + '",' +
              '"centralUrl":"{#CentralUrl}"}';
    SaveStringToFile(ParamsFile, Params, False);
  end;

  WizardForm.StatusLabel.Caption := 'Setting up DOXamigo (database, services, firewall)...';
  Exec('powershell.exe',
    '-NoProfile -ExecutionPolicy Bypass -File "' + ExpandConstant('{app}\setup\setup.ps1') + '" -Root "' + ExpandConstant('{app}') + '" -ParamsFile "' + ParamsFile + '"',
    '', SW_HIDE, ewWaitUntilTerminated, Code);

  Lines := '';
  if Code <> 0 then
    Lines := 'Setup did not finish (code ' + IntToStr(Code) + ').' + #13#10 +
             'Details: ' + ExpandConstant('{app}\data\logs\setup.log') + #13#10 +
             'Send that file to DOXamigo support.' + #13#10#13#10;

  Login := ExpandConstant('{app}\data\first-login.txt');
  if LoadStringFromFile(Login, Text) then
    Lines := Lines + String(Text) + #13#10;

  Addresses := ExpandConstant('{app}\data\addresses.txt');
  if LoadStringFromFile(Addresses, Text) then
    Lines := Lines + String(Text);

  ResultPage.RichEditViewer.Lines.Text := Lines;
end;

procedure DeinitializeSetup();
begin
  { The temporary password must not stay on disk. ({app} is unknown if setup was cancelled early.) }
  try
    DeleteFile(ExpandConstant('{app}\data\first-login.txt'));
  except
  end;
end;
