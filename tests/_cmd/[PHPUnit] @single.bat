@echo off
call %~dp0_config.bat

REM testgroup.bat [vendor_dir] [extra] [infile] [group]
%RUNNER_DIR%\testgroup.bat %VENDOR_DIR% "" single %1
