import { NodeIO } from '@gltf-transform/core';
import { KHRONOS_EXTENSIONS } from '@gltf-transform/extensions';
import { prune } from '@gltf-transform/functions';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const fdiList = [
    '18', '17', '16', '15', '14', '13', '12', '11', '21', '22', '23', '24', '25', '26', '27', '28',
    '48', '47', '46', '45', '44', '43', '42', '41', '31', '32', '33', '34', '35', '36', '37', '38',
];

function isSoftTissueNode(nodeName) {
    const lower = nodeName.toLowerCase();
    return (
        lower.includes('gum') ||
        lower.includes('gingiva') ||
        lower.includes('jaw') ||
        lower.includes('tongue') ||
        lower.includes('mouth') ||
        lower.includes('base') ||
        lower.includes('soft') ||
        lower.includes('wet') ||
        lower.includes('object_4') ||
        lower.includes('object_8')
    );
}

function isIgnoredNode(nodeName) {
    const lower = nodeName.toLowerCase();
    return lower.includes('cube') || lower.includes('bounds');
}

async function optimizeGLB() {
    const io = new NodeIO().registerExtensions(KHRONOS_EXTENSIONS);
    const sourcePath = path.join(__dirname, '../public/models/teeth-seperated.glb');
    const outDir = path.join(__dirname, '../public/models/optimized');
    
    if (!fs.existsSync(outDir)) {
        fs.mkdirSync(outDir, { recursive: true });
    }

    console.log(`Reading source file: ${sourcePath}`);
    const document = await io.read(sourcePath);
    
    // We will do this efficiently: 
    // 1. Identify which node belongs to which tooth
    // 2. Identify arch/base nodes
    
    const root = document.getRoot();
    let autoIndex = 0;
    
    const archNodes = [];
    const toothNodes = new Map(); // tooth_number -> Node[]
    
    root.listNodes().forEach((node) => {
        const name = node.getName() || '';
        if (node.getMesh()) {
            if (isIgnoredNode(name)) {
                // Skip completely
            } else if (isSoftTissueNode(name)) {
                archNodes.push(node);
            } else {
                if (autoIndex < fdiList.length) {
                    const num = fdiList[autoIndex];
                    if (!toothNodes.has(num)) {
                        toothNodes.set(num, []);
                    }
                    toothNodes.get(num).push(node);
                } else {
                    // Leftover meshes, just put them in the arch base so they aren't lost
                    archNodes.push(node);
                }
                autoIndex++;
            }
        } else {
            // Non-mesh nodes (cameras, lights, empty groups) - keep them in the arch if they are root level
            if (!node.getParentNode()) {
                 // But wait, gltf-transform nodes can be grouped.
            }
        }
    });

    console.log(`Found ${archNodes.length} arch/soft tissue nodes.`);
    console.log(`Identified meshes for ${toothNodes.size} teeth.`);

    // CREATE ARCH BASE
    console.log('Generating arch_base.glb...');
    const archDoc = await io.read(sourcePath); // Fresh copy
    const archRoot = archDoc.getRoot();
    
    // Remove all teeth meshes from arch base
    let archMeshKeepCount = 0;
    archRoot.listNodes().forEach((node) => {
        if (node.getMesh()) {
            const name = node.getName() || '';
            if (isIgnoredNode(name)) {
                 node.setMesh(null);
            } else if (!isSoftTissueNode(name)) {
                 node.setMesh(null); // Remove teeth meshes
            } else {
                 archMeshKeepCount++; // Keep soft tissue
            }
        }
    });
    
    // Clean up unused materials/meshes
    archDoc.getRoot().listMeshes().forEach(m => {
        if (m.listParents().length === 1) { // Only attached to root, no nodes
            m.dispose();
        }
    });
    
    await archDoc.transform(prune());
    await io.write(path.join(outDir, 'arch_base.glb'), archDoc);
    console.log(`Saved arch_base.glb. (Kept ${archMeshKeepCount} meshes)`);

    // CREATE INDIVIDUAL TEETH
    for (const [toothNum, nodes] of toothNodes.entries()) {
        const toothDoc = await io.read(sourcePath);
        const tRoot = toothDoc.getRoot();
        
        let localAutoIndex = 0;
        let found = false;
        
        tRoot.listNodes().forEach((node) => {
            if (node.getMesh()) {
                const name = node.getName() || '';
                if (!isSoftTissueNode(name) && !isIgnoredNode(name)) {
                    if (localAutoIndex === fdiList.indexOf(toothNum)) {
                        node.setExtras({ ...node.getExtras(), toothNumber: toothNum });
                        found = true;
                    } else {
                        node.setMesh(null);
                    }
                    localAutoIndex++;
                } else {
                    node.setMesh(null);
                }
            }
        });
        
        // Optimize: dispose unused meshes and materials
        tRoot.listMeshes().forEach(m => {
            if (m.listParents().length === 1) m.dispose(); // Only root parent
        });
        
        if (found) {
            await toothDoc.transform(prune());
            await io.write(path.join(outDir, `tooth-${toothNum}.glb`), toothDoc);
            console.log(`Saved tooth-${toothNum}.glb`);
        }
    }
    
    console.log('Optimization complete!');
}

optimizeGLB().catch(console.error);
