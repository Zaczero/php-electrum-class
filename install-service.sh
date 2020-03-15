cat > electrum.service <<EOF
[Unit]
Description=Electrum Service
After=network.target

[Service]
Type=oneshot
RemainAfterExit=yes
User=$USER
WorkingDirectory=$HOME
ExecStart=/usr/local/bin/electrum daemon start
ExecStartPost=/usr/local/bin/electrum daemon load_wallet
ExecStop=/usr/local/bin/electrum daemon stop

[Install]
WantedBy=multi-user.target
EOF

sudo cp electrum.service /lib/systemd/system
rm electrum.service

sudo systemctl daemon-reload
sudo systemctl enable electrum