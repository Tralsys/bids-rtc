import {
	createContext,
	memo,
	PropsWithChildren,
	useContext,
	useEffect,
	useState,
} from "react";
import { Role, RTCConnectionManager } from "../webrtc/ConnectionManager";

const Context = createContext<RTCConnectionManager | null>(null);
// eslint-disable-next-line react-refresh/only-export-components
export function useRTCConnectionManager() {
	return useContext(Context);
}

type WebRTCContextProps = {
	role: Role;
};
export default memo<PropsWithChildren<WebRTCContextProps>>(
	function WebRTCContext({ children, role }) {
		const [connManager, setConnManager] = useState<RTCConnectionManager | null>(
			null
		);

		useEffect(() => {
			try {
				const _connManager = new RTCConnectionManager(role);
				setConnManager(_connManager);
				return () => {
					setConnManager(null);
					_connManager.Dispose();
				};
			} catch (e) {
				console.error("Failed to start WebRTC", e);
				alert("Failed to start WebRTC");
			}
		}, [role]);

		return <Context.Provider value={connManager}>{children}</Context.Provider>;
	}
);
