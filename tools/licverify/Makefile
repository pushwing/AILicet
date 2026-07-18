# AILICET 라이센스 검증기(내부 테스트용) 빌드
#
# make mac     → 현재 머신(mac)에서 네이티브 빌드
# make test    → 순수 로직(verify/client) 유닛 테스트
# make run     → 로컬에서 바로 실행

BINARY := licverify

.PHONY: mac test run clean

mac:
	go build -o $(BINARY) .
	@echo "생성: $(BINARY) (네이티브 mac 빌드)"

test:
	go test ./verify/... ./client/... -v

run:
	go run .

clean:
	rm -f $(BINARY)
