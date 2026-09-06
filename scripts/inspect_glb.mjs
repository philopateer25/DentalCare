import { NodeIO } from '@gltf-transform/core';
import { KHRONOS_EXTENSIONS } from '@gltf-transform/extensions';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

async function inspectGLB() {
    const io = new NodeIO().registerExtensions(KHRONOS_EXTENSIONS);
    const document = await io.read(path.join(__dirname, '../public/models/teeth-seperated.glb'));
    const root = document.getRoot();

    console.log('Nodes in GLB:');
    root.listNodes().forEach((node, index) => {
        if (node.getMesh()) {
            console.log(`- Mesh Node [${index}]: ${node.getName() || 'Unnamed'}`);
        }
    });
}

inspectGLB().catch(console.error);
