@echo off
call %~dp0_config.bat

REM coverage.bat [vendor_dir] [extra] [coverage_dir]
%RUNNER_DIR%\coverage.bat %VENDOR_DIR% "" ..\_coverage
