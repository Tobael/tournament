{
  inputs = {
    nixpkgs.url = "github:nixos/nixpkgs/nixos-25.11";
  };

  outputs =
    { self, nixpkgs }:
    let
      system = "x86_64-linux";
    in
    {
      devShells.${system}.default =
        let
          pkgs = import nixpkgs { inherit system; };
        in
        pkgs.mkShell {
          name = "composer-devShell";
          buildInputs = [
            pkgs.php84Packages.composer
            (pkgs.php84.buildEnv {
              extraConfig = "upload_max_filesize = 10M";
            })
            pkgs.nodejs_22
            pkgs.nodePackages.npm
          ];
        };
    };
}
