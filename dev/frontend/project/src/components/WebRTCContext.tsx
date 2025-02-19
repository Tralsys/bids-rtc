import { memo, PropsWithChildren, useEffect, useRef } from "react";
import { Role, RTCConnectionManager } from "../webrtc/ConnectionManager";

type WebRTCContextProps = {
	role: Role;
};
export default memo<PropsWithChildren<WebRTCContextProps>>(
	function WebRTCContext({ children, role }) {
		const ref = useRef<RTCConnectionManager | null>(null);
		useEffect(() => {
			try {
				const connManager = new RTCConnectionManager(role);
				ref.current = connManager;
				return () => {
					ref.current = null;
					connManager.Dispose();
				};
			} catch (e) {
				console.error("Failed to start WebRTC", e);
				alert("Failed to start WebRTC");
			}
		}, [role]);
		return children;
	}
);
