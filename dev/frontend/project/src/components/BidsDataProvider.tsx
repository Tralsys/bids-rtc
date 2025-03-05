import {
	createContext,
	memo,
	PropsWithChildren,
	useEffect,
	useRef,
} from "react";
import { BIDSSharedMemoryData } from "../webrtc/BIDSSharedMemoryData";
import { useRTCConnectionManager } from "./WebRTCContext";

type BidsDataProviderContextType = {
	bsmd?: BIDSSharedMemoryData;
	panel?: number[];
	sound?: number[];
};
const Context = createContext<BidsDataProviderContextType | null>(null);

export default memo<PropsWithChildren>(function BidsDataProvider({ children }) {
	const rtc = useRTCConnectionManager();
	const valueRef = useRef<BidsDataProviderContextType>({});

	useEffect(() => {}, [rtc]);
	return (
		<Context.Provider value={valueRef.current}>{children}</Context.Provider>
	);
});
