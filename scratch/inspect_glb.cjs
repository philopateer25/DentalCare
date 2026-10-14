const fs = require('fs');

function generateCompleteMeshMap(filePath) {
  const buf = fs.readFileSync(filePath);
  const jsonLen = buf.readUInt32LE(12);
  const jsonStr = buf.toString('utf8', 20, 20 + jsonLen);
  const gltf = JSON.parse(jsonStr);

  const globalMin = [ -0.05234730616211891, -0.08060351014137268, -0.07003773748874664 ];
  const globalMax = [ 0.05234730616211891, 0.03399186208844185, 0.050519589334726334 ];
  const center = [
    (globalMin[0] + globalMax[0]) / 2,
    (globalMin[1] + globalMax[1]) / 2,
    (globalMin[2] + globalMax[2]) / 2,
  ];
  const size = [
    globalMax[0] - globalMin[0],
    globalMax[1] - globalMin[1],
    globalMax[2] - globalMin[2],
  ];
  const maxDim = Math.max(...size);
  const scaleFactor = 6.5 / maxDim;

  const upperFDI = [
    { tooth: '18', minAngle: -70, maxAngle: -58 },
    { tooth: '17', minAngle: -58, maxAngle: -51 },
    { tooth: '16', minAngle: -51, maxAngle: -43 },
    { tooth: '15', minAngle: -43, maxAngle: -34 },
    { tooth: '14', minAngle: -34, maxAngle: -25 },
    { tooth: '13', minAngle: -25, maxAngle: -17 },
    { tooth: '12', minAngle: -17, maxAngle: -9 },
    { tooth: '11', minAngle: -9, maxAngle: 0 },
    { tooth: '21', minAngle: 0, maxAngle: 9 },
    { tooth: '22', minAngle: 9, maxAngle: 17 },
    { tooth: '23', minAngle: 17, maxAngle: 25 },
    { tooth: '24', minAngle: 25, maxAngle: 34 },
    { tooth: '25', minAngle: 34, maxAngle: 43 },
    { tooth: '26', minAngle: 43, maxAngle: 52 },
    { tooth: '27', minAngle: 52, maxAngle: 60 },
    { tooth: '28', minAngle: 60, maxAngle: 75 },
  ];

  const lowerFDI = [
    { tooth: '48', minAngle: -125, maxAngle: -112 },
    { tooth: '47', minAngle: -112, maxAngle: -96 },
    { tooth: '46', minAngle: -96, maxAngle: -70 },
    { tooth: '45', minAngle: -70, maxAngle: -49 },
    { tooth: '44', minAngle: -49, maxAngle: -34 },
    { tooth: '43', minAngle: -34, maxAngle: -24 },
    { tooth: '42', minAngle: -24, maxAngle: -12 },
    { tooth: '41', minAngle: -12, maxAngle: 0 },
    { tooth: '31', minAngle: 0, maxAngle: 12 },
    { tooth: '32', minAngle: 12, maxAngle: 22 },
    { tooth: '33', minAngle: 22, maxAngle: 34 },
    { tooth: '34', minAngle: 34, maxAngle: 52 },
    { tooth: '35', minAngle: 52, maxAngle: 70 },
    { tooth: '36', minAngle: 70, maxAngle: 96 },
    { tooth: '37', minAngle: 96, maxAngle: 108 },
    { tooth: '38', minAngle: 108, maxAngle: 125 },
  ];

  const meshToTooth = {};

  gltf.nodes.forEach((node) => {
    if (node.mesh === undefined || node.name === 'Cube' || node.name === 'Object_4' || node.name === 'Object_8') return;
    const mesh = gltf.meshes[node.mesh];
    const posAccessorIdx = mesh.primitives[0]?.attributes?.POSITION;
    if (posAccessorIdx === undefined) return;

    const acc = gltf.accessors[posAccessorIdx];
    const min = acc.min || [0, 0, 0];
    const max = acc.max || [0, 0, 0];
    const rawCenterY = (min[1] + max[1]) / 2;
    const rawCenterX = (min[0] + max[0]) / 2;
    const rawCenterZ = (min[2] + max[2]) / 2;

    const scaledX = (rawCenterX - center[0]) * scaleFactor;
    const scaledY = (rawCenterY - center[1]) * scaleFactor;
    const scaledZ = (rawCenterZ - center[2]) * scaleFactor;

    const angleDeg = (Math.atan2(scaledX, scaledZ) * 180) / Math.PI;

    if (scaledY > 0.6) {
      // Upper
      const match = upperFDI.find(b => angleDeg >= b.minAngle && angleDeg < b.maxAngle);
      meshToTooth[node.name] = match ? match.tooth : '11';
    } else {
      // Lower
      const match = lowerFDI.find(b => angleDeg >= b.minAngle && angleDeg < b.maxAngle);
      meshToTooth[node.name] = match ? match.tooth : '31';
    }
  });

  console.log('const TOOTH_MESH_MAP: Record<string, string> =', JSON.stringify(meshToTooth, null, 2));

  // Verify all 32 teeth are represented
  const toothCounts = {};
  Object.values(meshToTooth).forEach(t => {
    toothCounts[t] = (toothCounts[t] || 0) + 1;
  });
  console.log('\nTooth mesh distribution:');
  console.log(toothCounts);
  console.log('Distinct teeth mapped:', Object.keys(toothCounts).length, '/ 32');
}

generateCompleteMeshMap('public/models/teeth-seperated.glb');
