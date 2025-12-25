{
  inputs.nixpkgs.url = "github:nixos/nixpkgs/nixos-25.11";

  outputs =
    { self, nixpkgs }:
    let
      system = "x86_64-linux";
      pkgs = import nixpkgs { inherit system; };
    in
    {
      devShells.${system}.default = pkgs.mkShell {
        buildInputs = with pkgs; [
          elixir
          erlang
          elixir-ls
          inotify-tools
          nodejs
          yarn
          postgresql
        ];

        shellHook = ''
          PROJECT_ROOT="$(git rev-parse --show-toplevel)"
          mkdir -p .nix-mix
          mkdir -p .nix-hex
          export MIX_HOME=$PROJECT_ROOT/.nix-mix
          export HEX_HOME=$PROJECT_ROOT/.nix-hex
          export PATH=$MIX_HOME/bin:$PATH
          export PATH=$HEX_HOME/bin:$PATH
          export ERL_AFLAGS="-kernel shell_history enabled"

          set -e
          export PGDIR=$PROJECT_ROOT/postgres
          export PGHOST=$PGDIR
          export PGDATA=$PGDIR/data
          export PGLOG=$PGDIR/log
          export DATABASE_URL="postgresql:///postgres?host=$PGDIR"

          if [ ! -d $PGDIR ]; then
            mkdir $PGDIR
          fi

          if [ ! -d $PGDATA ]; then
            initdb $PGDATA --auth=trust >/dev/null
          fi
        '';
      };

    };
}
