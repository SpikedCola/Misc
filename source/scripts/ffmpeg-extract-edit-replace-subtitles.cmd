@echo on

for %%i in ("*.mp4") do (
	echo %%i
	rem extract subs
	ffmpeg -y -i "%%i" -map 0:s:0 sub0.srt

	rem edit subs
	rem cant seem to prevent sed from making a sedXXXXX file on windows :(
	sed -i "s/\\h//g" sub0.srt
	
	if not exist "fixed" mkdir fixed
	
	rem put back subs
	ffmpeg -i "%%i" -i sub0.srt -map 0 -map -0:s -map 1 -c copy -c:s mov_text -metadata:s:s:0 language=eng "fixed\%%i"
	
	rem cleanup
	rm sub0.srt
)