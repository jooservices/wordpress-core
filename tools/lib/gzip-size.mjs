import { gzipSync } from 'node:zlib';

/** Returns the byte length of a Node gzipSync() result for asset manifests and budget checks. */
export const gzipSize = (buffer) => gzipSync(buffer).byteLength;
