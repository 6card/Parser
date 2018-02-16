@echo off
@setlocal
set PARSER_PATH=%~dp0
if "%PHP_COMMAND%" == "" set PHP_COMMAND=php.exe
"%PHP_COMMAND%" "%PARSER_PATH%parser" %*
@endlocal