@ECHO OFF

rem phpstan modified to pick out working directory from last argument
rem and include a configuration file in that directory. this is so we 
rem can load inc.php relative to the project dir.

rem get last argument.
rem last could be root or anywhere in path. get x\www\projectname from that string.
for %%a in (%*) do set last=%%a

set result=

rem strip quotes from last, then add them back below in for ()
set last=%last:"=%

rem get first 4 tokens in string separated by \ 
for /f "tokens=1,2,3,4 delims=\" %%a in ("%last%") do (
	if "%%b"=="www" (
		set result=%%a\%%b\%%c
	) else if "%%c"=="www" ( 
		set result=%%a\%%b\%%c\%%d
	)
)

setlocal DISABLEDELAYEDEXPANSION
SET BIN_TARGET=%~dp0/phpstan
SET COMPOSER_RUNTIME_BIN_DIR=%~dp0

php "%BIN_TARGET%" --configuration="%result%\phpstan.neon" %*