import { BIDSSharedMemoryData } from "../webrtc/BIDSSharedMemoryData";

export type BidsDataProviderContextType = {
	isConnected?: boolean;
	bsmd?: BIDSSharedMemoryData;
	panel?: number[];
	sound?: number[];

	_bsmdListenerList: ((bsmd: BIDSSharedMemoryData) => void)[];
	_panelListenerList: ((panel: number[]) => void)[];
	_soundListenerList: ((sound: number[]) => void)[];
};
