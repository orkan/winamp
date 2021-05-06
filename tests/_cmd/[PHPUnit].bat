@echo off
call %~dp0_config.bat

REM test.bat [vendor_dir] [extra] [infile]
%RUNNER_DIR%\test.bat %VENDOR_DIR% "" %1
