const { Document, NodeIO } = require('@gltf-transform/core');
const { KHRDracoMeshCompression } = require('@gltf-transform/extensions');
const draco3d = require('draco3dgltf');

async function checkNames() {
  const io = new NodeIO()
    .registerExtensions([KHRDracoMeshCompression])
    .registerDependencies({
      'draco3d.decoder': await draco3d.createDecoderModule(),
      'draco3d.encoder': await draco3d.createEncoderModule(),
    });
  
  const document = await io.read('public/models/teeth-seperated-optimized.glb');
  
  const nodes = document.getRoot().listNodes();
  const names = nodes.map(n => n.getName());
  
  console.log('--- NODE NAMES ---');
  console.log(names.filter(n => n).slice(0, 50).join(', '));
}

checkNames();
