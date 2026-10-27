!macro customInstall
  SetOutPath "$PLUGINSDIR"
  File /oname=VC_redist.x64.exe "${BUILD_RESOURCES_DIR}\VC_redist.x64.exe"
  ExecWait '"$PLUGINSDIR\VC_redist.x64.exe" /install /quiet /norestart' $0
  ${If} $0 == 3010
    SetRebootFlag true
  ${EndIf}
  ${If} $0 != 0
  ${AndIf} $0 != 1638
  ${AndIf} $0 != 3010
    MessageBox MB_ICONSTOP "Microsoft Visual C++ Runtime gagal dipasang (kode $0). Jalankan installer kembali setelah masalah Windows diselesaikan."
    Abort
  ${EndIf}
!macroend
