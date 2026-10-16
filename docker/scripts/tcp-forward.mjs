// Forwards 127.0.0.1:<local port> to <host>:<remote port>. Used by e2e-vm.sh so the Playwright server on the Dev VM
// sees a loopback Host header (it answers 403 to other host names).
import net from 'node:net';

const [localPort, host, remotePort] = process.argv.slice(2);
net
  .createServer((client) => {
    const upstream = net.connect(Number(remotePort), host);
    const close = () => {
      client.destroy();
      upstream.destroy();
    };
    client.on('error', close);
    upstream.on('error', close);
    client.pipe(upstream).pipe(client);
  })
  .listen(Number(localPort), '127.0.0.1');
