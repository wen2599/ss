
# To learn more about how to use Nix to configure your environment
# see: https://firebase.google.com/docs/studio/customize-workspace
{ pkgs, ... }: {
  # Which nixpkgs channel to use.
  channel = "stable-24.05"; # or "unstable"

  # Use https://search.nixos.org/packages to find packages
  packages = [
    pkgs.php
    pkgs.mariadb
    pkgs.phpHttpd
    pkgs.composer
  ];

  # Sets environment variables in the workspace
  env = {
    DB_HOST = "127.0.0.1";
    DB_PORT = "3306";
    DB_DATABASE = "dev_db";
    DB_USER = "root";
    DB_PASSWORD = ""; # Default password for new MariaDB install is empty
  };

  idx = {
    # Search for the extensions you want on https://open-vsx.org/ and use "publisher.id"
    extensions = [
      # "vscodevim.vim"
    ];

    # Enable previews
    previews = {
      enable = true;
      previews = {
        web = {
          command = ["npm", "--prefix", "frontend", "run", "dev"];
          manager = "web";
        };
        backend = {
          command = ["php", "-S", "127.0.0.1:8080", "-t", "backend/api"];
          manager = "process";
        };
      };
    };

    # Workspace lifecycle hooks
    workspace = {
      # Runs when a workspace is first created
      onCreate = {
        npm-install = "npm --prefix frontend install";
        composer-install = "composer --working-dir=backend install";
        # Initialize the database directory
        init-db = ''
          mkdir -p $HOME/mysql_data
          mariadb-install-db --user=$USER --datadir=$HOME/mysql_data
        '';
      };
      # Runs when the workspace is (re)started
      onStart = {
        # Start the database daemon
        start-db = "mariadbd-safe --datadir=$HOME/mysql_data &";
      };
    };
  };
}
