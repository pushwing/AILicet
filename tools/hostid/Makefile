# AILICET 호스트ID 유틸리티 빌드
#
# make            → mac(universal) + windows 실행파일 생성 (dist/)
# make mac        → macOS universal 바이너리
# make windows    → Windows amd64 실행파일
# make test       → 단위 테스트
# make clean      → dist 삭제

BINARY := hostid
DIST    := dist

.PHONY: all mac windows test sign clean

all: mac windows

mac:
	@mkdir -p $(DIST)
	GOOS=darwin GOARCH=amd64 go build -o $(DIST)/$(BINARY)-mac-intel .
	GOOS=darwin GOARCH=arm64 go build -o $(DIST)/$(BINARY)-mac-arm .
	@# Apple Silicon·Intel 통합 바이너리(빌드 머신에 lipo 있을 때)
	@lipo -create -output $(DIST)/$(BINARY)-mac \
		$(DIST)/$(BINARY)-mac-intel $(DIST)/$(BINARY)-mac-arm 2>/dev/null \
		&& rm -f $(DIST)/$(BINARY)-mac-intel $(DIST)/$(BINARY)-mac-arm \
		&& echo "생성: $(DIST)/$(BINARY)-mac (universal)" \
		|| echo "lipo 없음 — intel/arm 개별 바이너리 유지"

windows:
	@mkdir -p $(DIST)
	GOOS=windows GOARCH=amd64 go build -o $(DIST)/$(BINARY).exe .
	@echo "생성: $(DIST)/$(BINARY).exe"

test:
	go test ./...

# 배포용 코드사이닝(자격증명은 환경변수로 주입 — sign.sh 헤더 참고)
sign:
	./sign.sh all

clean:
	rm -rf $(DIST)
