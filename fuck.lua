#!/usr/bin/lua

arg_types = {
  raw = 1,--"raw",
  var = 2,--"var",
  label = 3,--"label",
}

local arg_table = {
  table_init = {arg_types.raw},
  table_set = {arg_types.var,arg_types.raw},
  table_mod = {arg_types.var,arg_types.var},
  table_input = {arg_types.var},
  table_output = {arg_types.var},
  label_define = {arg_types.raw},
  label_jump = {arg_types.label},
  label_branch = {arg_types.var,arg_types.label},
}

local command_keywords = {
  table_init = "fuck",
  table_set = "fucking",
  table_mod = "fucked",
  table_input = "unfucking",
  table_output = "unfucked",
  label_define = "motherfuck",
  label_jump = "motherfucking",
  label_branch = "motherfucked",
}

local commands = {
  table_init = {
    op = function(args)
      vars[string.lower(args[1])] = ""
    end,
  },
  table_set = {
    op = function(args)
      assert(vars[args[1]],
        "ERROR["..pc.."] var args[0] <"..args[1].."> is not initialized.")
      vars[args[1]] = args[2]
    end,
  },
  table_mod = {
    op = function(args)
      assert(vars[args[1]],
        "ERROR["..pc.."] var args[0] <"..args[1].."> is not initialized.")
      assert(vars[args[2]],
        "ERROR["..pc.."] var args[1] <"..args[2].."> is not initialized.")
      local n0 = tonumber(vars[args[1]])
      local n1 = tonumber(vars[args[2]])
      if n0 and n1 then
        vars[args[1]] = vars[args[1]] + vars[args[2]]
      else
        vars[args[1]] = tostring(vars[args[1]]) .. " " .. tostring(vars[args[2]])
      end
    end,
  },
  table_input = {
    op = function(args)
      assert(vars[args[1]],
        "ERROR["..pc.."] var args[0] <"..args[1].."> is not initialized.")
      vars[args[1]] = io.read()
    end,
  },
  table_output = {
    op = function(args)
      assert(vars[args[1]],
        "ERROR["..pc.."] var args[0] <"..args[1].."> is not initialized.")
      local tmp = string.gsub(vars[args[1]],'\\n', "\n")
      io.write(tmp)
    end,

  },
  label_define = {
    op = function(args)
      -- this function has been done pre-emptively
    end,
  },
  label_jump = {
    op = function(args)
      assert(labels[args[1]],
        "ERROR["..pc.."] label args[0] <"..args[1].."> is not initialized.")
      pc = labels[args[1]]+1
    end,
  },
  label_branch = {
    op = function(args)
      assert(vars[args[1]],
        "ERROR["..pc.."] var args[0] <"..args[1].."> is not initialized.")
      assert(labels[args[2]],
        "ERROR["..pc.."] label args[1] <"..args[2].."> is not initialized.")
      if tonumber(vars[args[1]]) ~= 0 then
        pc = labels[args[2]]
      end

    end,
  },
}

function usage()
  print("Usage: "..tostring(arg[0]).." [options] input\n")
end

config = {}
for i,v in pairs(arg) do
  if i > 0 then
    if v == "-h" or v == "--help" then
      usage()
      return
    elseif v == "-d" or v == "--debug" then
    elseif v == "-c" or v == "--clean" then
      for keyword,map in pairs(command_keywords) do
        command_keywords[keyword] = keyword
      end
    else
      input = v
      break
    end
  end
end

-- make inverse lookup table
local command_keywords_inv = {}
for i,v in pairs(command_keywords) do
  command_keywords_inv[v] = i
end

-- add keywords to objects for reference
for keyword,command in pairs(commands) do
  command.keyword = command_keywords[keyword]
end

local program = {}

if input then
  input_file = io.open(input,'r')
  if input_file then
    local current = nil
    repeat
      local char = input_file:read(1)
      if char ~= nil then
        if char == "\n" or char == "\r" or char == " " then
          table.insert(program,current)
          current = nil
        else
          current = (current or "") .. char
        end
      else
        table.insert(program,current)
        current = nil
      end
    until char == nil
    io.close(input_file)
  else
    print(tostring(arg[0])..": error: "..tostring(input)..": No such file")
  end
else
  usage()
end

labels = {}
for pc,word in pairs(program) do
  if word == command_keywords.label_define then
    --print("Found label:",program[pc+1])
    labels[ program[pc+1] ] = pc
  end
end

pc = 1
vars = {}
local arg_stack = {}
local current_command_keyword = nil
--local current_command = nil

while pc <= #program do

  local current_line = string.lower(program[pc])

  --print("current line:",current_line)

  if current_command_keyword then

    --print("current command:",current_command_keyword)

    local var_type = arg_table[current_command_keyword][#arg_stack+1]

    --print("var type:",var_type)

    if var_type == arg_types.raw then
      table.insert(arg_stack,program[pc])
    elseif var_type == arg_types.var then
      if vars[current_line] then
        table.insert(arg_stack,current_line)
      end
    elseif var_type == arg_types.label then
      if labels[current_line] then
        table.insert(arg_stack,current_line)
      end
    end

    --print("#arg_stack",#arg_stack,"#arg_table[current_command_keyword]",#arg_table[current_command_keyword])

    if #arg_stack == #arg_table[current_command_keyword] then
      --print(">call stack")
      --for i,v in pairs(arg_stack) do print('>>',i,v) end
      commands[current_command_keyword].op(arg_stack) -- this is a function
      current_command_keyword = nil
      arg_stack = {}
    end

  else -- no current command
    current_command_keyword = command_keywords_inv[current_line]
    --print(">new command",current_command_keyword)
  end

  pc=pc+1

  --print("")

end
io.write("\n")
