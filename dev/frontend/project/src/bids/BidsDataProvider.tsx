import {
	createContext,
	memo,
	PropsWithChildren,
	useCallback,
	useContext,
	useEffect,
	useRef,
} from "react";
import { useRTCConnectionManager } from "../components/WebRTCContext";
import { BidsDataProviderContextType } from "./BidsDataProviderType";
import {
	DATA_CHANNEL_LABEL,
	DataChannelStateChangedEventArgs,
	DataGotEventArgs,
} from "../webrtc/ConnectionManager";
import { deserializeBIDSSharedMemoryData } from "../webrtc/BIDSSharedMemoryData";

const Context = createContext<BidsDataProviderContextType | null>(null);
// eslint-disable-next-line react-refresh/only-export-components
export function useBidsDataProviderContextValue() {
	return useContext(Context);
}

// trW
const HEADER_0 = 0x74;
const HEADER_1 = 0x72;
const HEADER_2 = 0x57;

const HEADER_3_BSMD = 0x42; // B
const HEADER_3_PANEL = 0x50; // P
const HEADER_3_SOUND = 0x53; // S

export default memo<PropsWithChildren>(function BidsDataProvider({ children }) {
	const rtc = useRTCConnectionManager();
	const valueRef = useRef<BidsDataProviderContextType>({
		_bsmdListenerList: [],
		_panelListenerList: [],
		_soundListenerList: [],
	});

	const onDataGot = useCallback((e: DataGotEventArgs) => {
		if (e.data.byteLength < 4) {
			return;
		}
		const header = new Uint8Array(e.data, 0, 4);

		if (
			header[0] !== HEADER_0 ||
			header[1] !== HEADER_1 ||
			header[2] !== HEADER_2
		) {
			return;
		}

		switch (header[3]) {
			case HEADER_3_BSMD: {
				const bsmd = deserializeBIDSSharedMemoryData(
					new DataView(e.data.slice(4))
				);
				valueRef.current.bsmd = bsmd;
				valueRef.current._bsmdListenerList.forEach((listener) => {
					listener(bsmd);
				});
				break;
			}
			case HEADER_3_PANEL: {
				const length = new DataView(e.data, 4, 4).getUint32(0, true);
				const dataArray = new Int32Array(e.data, 8, length);
				const panel = Array.from(dataArray);
				valueRef.current.panel = panel;
				valueRef.current._panelListenerList.forEach((listener) => {
					listener(panel);
				});
				break;
			}
			case HEADER_3_SOUND: {
				const length = new DataView(e.data, 4, 4).getUint32(0, true);
				const dataArray = new Int32Array(e.data, 8, length);
				const sound = Array.from(dataArray);
				valueRef.current.sound = sound;
				valueRef.current._soundListenerList.forEach((listener) => {
					listener(sound);
				});
				break;
			}
		}
	}, []);

	useEffect(() => {
		const onOpen = (e: DataChannelStateChangedEventArgs) => {
			startListeningBidsData(e.dataChannel);
		};
		rtc?.addDataGotEventListener(onDataGot);
		rtc?.addDataChannelOpenEventListener(onOpen);
		Object.values(rtc?._dataChannelMap ?? {}).forEach((dcMap) => {
			const dc = dcMap[DATA_CHANNEL_LABEL];
			if (dc?.readyState === "open") {
				startListeningBidsData(dc);
			}
		});
		return () => {
			rtc?.removeDataGotEventListener(onDataGot);
			rtc?.removeDataChannelOpenEventListener(onOpen);
			Object.values(rtc?._dataChannelMap ?? {}).forEach((dcMap) => {
				const dc = dcMap[DATA_CHANNEL_LABEL];
				if (dc?.readyState === "open") {
					endListeningBidsData(dc);
				}
			});
		};
	}, [onDataGot, rtc]);
	return (
		<Context.Provider value={valueRef.current}>{children}</Context.Provider>
	);
});

function startListeningBidsData(dc: RTCDataChannel) {
	console.log("startListeningBidsData", dc);
	dc.send(getWSCmd("BEGIN_BSMD"));
	dc.send(getWSCmd("BEGIN_PANEL"));
	dc.send(getWSCmd("BEGIN_SOUND"));
}

function endListeningBidsData(dc: RTCDataChannel) {
	console.log("endListeningBidsData", dc);
	dc.send(getWSCmd("END_BSMD"));
	dc.send(getWSCmd("END_PANEL"));
	dc.send(getWSCmd("END_SOUND"));
}

function getWSCmd(cmd: string) {
	return JSON.stringify({
		id: Math.random().toString(36).slice(8),
		cmd,
	});
}
