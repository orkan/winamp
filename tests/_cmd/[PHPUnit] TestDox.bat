@echo off
call %~dp0_config.bat

REM testdox.bat [vendor_dir] [extra] [infile]
%RUNNER_DIR%\testdox.bat %VENDOR_DIR% "" %1
