import { Configuration, SDPExchangeApi } from "@tralsys/bids-rtc-signaling-api";
import { IS_DOCKER_DEBUG, IS_LOCAL_DEBUG } from "../constants";
import { auth } from "../firebase/firebase";

export const xClientId = (() => {
	try {
		return crypto.randomUUID();
	} catch {
		const hex = Array.from(crypto.getRandomValues(new Uint8Array(16)))
			.map((b, i) => {
				if (i === 6) {
					b = (b & 0x0f) | 0x40;
				} else if (i === 8) {
					b = (b & 0x3f) | 0x80;
				}
				return b.toString(16).padStart(2, "0");
			})
			.join("");
		return [
			hex.substring(0, 8),
			hex.substring(8, 12),
			hex.substring(12, 16),
			hex.substring(16, 20),
			hex.substring(20),
		].join("-");
	}
})();

const apiConfig = new Configuration({
	basePath: IS_LOCAL_DEBUG
		? "http://localhost:8080/signaling"
		: IS_DOCKER_DEBUG
		? `${window.location.origin}/signaling`
		: undefined,
	accessToken: async () => {
		return (await auth.currentUser?.getIdToken()) ?? "";
	},
});

export const sdpExchangeApi = new SDPExchangeApi(apiConfig);
